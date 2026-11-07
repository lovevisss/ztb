<?php

namespace App\Http\Controllers;

use App\Models\ProcurementApplication;
use App\Models\ProcurementAttachment;
use App\Models\ProcurementClassificationRule;
use App\Services\ApprovalWorkflow;
use App\Services\TenderNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class TenderSectionsController extends Controller
{
    public function create(Request $request, TenderNumber $numbers): RedirectResponse
    {
        abort_if($request->user()->department_id === null, 422, '当前用户尚未关联部门');
        $section = $request->validate(['section' => ['required', Rule::in(['basic', 'items', 'budget', 'attachments'])]])['section'];
        $data = $this->sectionData($request, $section);
        $paths = [];
        try {
            $application = DB::transaction(function () use ($request, $numbers, $section, $data, &$paths) {
                $application = ProcurementApplication::create([
                    'applicant_id' => $request->user()->id,
                    'department_id' => $request->user()->department_id,
                    'application_form' => 'central_tender',
                    'status' => 'draft',
                    'project_number' => $numbers->next(),
                ]);
                $this->saveSection($request, $application, $section, $data, $paths);

                return $application;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }

        return to_route('procurement.tenders.edit', ['application' => $application->id, 'tab' => $section]);
    }

    public function update(Request $request, ProcurementApplication $application): RedirectResponse
    {
        $this->authorizeDraft($request, $application);
        $section = $request->validate(['section' => ['required', Rule::in(['basic', 'items', 'budget', 'attachments'])]])['section'];
        $data = $this->sectionData($request, $section);
        $paths = [];
        try {
            DB::transaction(fn () => $this->saveSection($request, $application, $section, $data, $paths));
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }

        return to_route('procurement.tenders.edit', ['application' => $application->id, 'tab' => $section]);
    }

    public function submit(Request $request, ProcurementApplication $application, ApprovalWorkflow $workflow): RedirectResponse
    {
        $this->authorizeDraft($request, $application);
        DB::transaction(function () use ($application, $workflow) {
            $application = ProcurementApplication::query()->lockForUpdate()->findOrFail($application->id);
            $errors = [];
            foreach (['title', 'category', 'contact_phone', 'procurement_requirements'] as $field) {
                if (blank($application->{$field})) {
                    $errors[$field] = '请填写此项。';
                }
            }
            if ($application->has_usage_department && $application->usage_department_id === null) {
                $errors['usage_department_id'] = '请选择使用部门。';
            }
            $items = DB::table('procurement_line_items')->where('application_id', $application->id);
            $total = (int) $items->sum('total_cents');
            if ($items->count() === 0 || $total <= 0) {
                $errors['items'] = '请添加有效的采购清单。';
            }
            if ((int) DB::table('procurement_budget_allocations')->where('application_id', $application->id)->sum('amount_cents') !== $total) {
                $errors['budget'] = '预算指标使用金额合计须与采购清单合计一致。';
            }
            $rule = ProcurementClassificationRule::effectiveFor($application->department_id);
            if ($total < $rule->threshold_cents) {
                $errors['items'] = '该金额应使用部门自行采购申请。';
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
            $application->update([
                'budget_cents' => $total,
                'purchase_type' => 'central_tender',
                'classification_rule_id' => $rule->id,
                'classification_threshold_cents' => $rule->threshold_cents,
                'submitted_at' => now(),
            ]);
            $workflow->start($application);
        });

        return to_route('procurement.applications.index');
    }

    public function catalogStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'specification' => ['nullable', 'string'],
            'unit' => ['nullable', 'string', 'max:50'],
            'unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'remarks' => ['nullable', 'string'],
        ]);
        DB::table('personal_catalog_items')->insert([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'specification' => $data['specification'] ?? null,
            'unit' => $data['unit'] ?? null,
            'unit_price_cents' => (int) round($data['unit_price'] * 100),
            'remarks' => $data['remarks'] ?? null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return back();
    }

    public function catalogUpdate(Request $request, int $item): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'specification' => ['nullable', 'string'],
            'unit' => ['nullable', 'string', 'max:50'], 'unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'remarks' => ['nullable', 'string'],
        ]);
        $affected = DB::table('personal_catalog_items')->where('id', $item)->where('user_id', $request->user()->id)->update([
            'name' => $data['name'], 'specification' => $data['specification'] ?? null,
            'unit' => $data['unit'] ?? null, 'unit_price_cents' => (int) round($data['unit_price'] * 100),
            'remarks' => $data['remarks'] ?? null, 'updated_at' => now(),
        ]);
        abort_if($affected === 0, 404);

        return back();
    }

    public function catalogDelete(Request $request, int $item): RedirectResponse
    {
        abort_if(DB::table('personal_catalog_items')->where('id', $item)->where('user_id', $request->user()->id)->delete() === 0, 404);

        return back();
    }

    public function syncBudgets(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['procurement_manager', 'system_admin']), 403);
        Artisan::call('procurement:sync-budgets');

        return back()->with('status', Artisan::output());
    }

    private function authorizeDraft(Request $request, ProcurementApplication $application): void
    {
        abort_unless($application->applicant_id === $request->user()->id, 403);
        abort_unless($application->application_form === 'central_tender' && in_array($application->status, ['draft', 'returned'], true), 403);
    }

    private function sectionData(Request $request, string $section): array
    {
        return match ($section) {
            'basic' => $request->validate([
                'title' => ['nullable', 'string', 'max:255'],
                'category' => ['nullable', Rule::in(['工程类', '货物类', '服务类'])],
                'contact_phone' => ['nullable', 'string', 'max:30'],
                'purchase_method' => ['nullable', 'string', 'max:255'],
                'is_special_purchase' => ['required', 'boolean'],
                'is_non_expenditure' => ['required', 'boolean'],
                'has_usage_department' => ['required', 'boolean'],
                'usage_department_id' => ['nullable', 'integer', 'exists:departments,id'],
                'procurement_requirements' => ['nullable', 'string'],
                'remarks' => ['nullable', 'string'],
            ]),
            'items' => $request->validate([
                'items' => ['present', 'array', 'max:500'],
                'items.*.name' => ['required', 'string', 'max:255'],
                'items.*.specification' => ['nullable', 'string'],
                'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
                'items.*.unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
                'items.*.unit' => ['nullable', 'string', 'max:50'],
                'items.*.remarks' => ['nullable', 'string'],
            ]),
            'budget' => $request->validate([
                'allocations' => ['present', 'array', 'max:100'],
                'allocations.*.budget_indicator_id' => ['required', 'integer', 'distinct', 'exists:budget_indicators,id'],
                'allocations.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
                'budget_basis_note' => ['nullable', 'string'],
            ]),
            'attachments' => $request->validate([
                'files' => ['nullable', 'array', 'max:10'],
                'files.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:20480'],
            ]),
        };
    }

    private function saveSection(Request $request, ProcurementApplication $application, string $section, array $data, array &$paths): void
    {
        if ($section === 'basic') {
            if (! $data['has_usage_department']) {
                $data['usage_department_id'] = null;
            }
            $application->update($data);
        } elseif ($section === 'items') {
            DB::table('procurement_line_items')->where('application_id', $application->id)->delete();
            $total = 0;
            foreach ($data['items'] as $index => $item) {
                $unitPrice = (int) round($item['unit_price'] * 100);
                $lineTotal = (int) round($unitPrice * $item['quantity']);
                $total += $lineTotal;
                DB::table('procurement_line_items')->insert([
                    'application_id' => $application->id, 'position' => $index + 1,
                    'name' => $item['name'], 'specification' => $item['specification'] ?? null,
                    'quantity' => $item['quantity'], 'unit' => $item['unit'] ?? null,
                    'unit_price_cents' => $unitPrice, 'total_cents' => $lineTotal,
                    'remarks' => $item['remarks'] ?? null, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $application->update(['budget_cents' => $total]);
        } elseif ($section === 'budget') {
            DB::table('procurement_budget_allocations')->where('application_id', $application->id)->delete();
            foreach ($data['allocations'] as $allocation) {
                $indicator = DB::table('budget_indicators')->find($allocation['budget_indicator_id']);
                DB::table('procurement_budget_allocations')->insert([
                    'application_id' => $application->id, 'budget_indicator_id' => $indicator->id,
                    'code' => $indicator->code, 'name' => $indicator->name, 'director' => $indicator->director,
                    'amount_cents' => (int) round($allocation['amount'] * 100),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $application->update(['budget_basis_note' => $data['budget_basis_note'] ?? null]);
        } else {
            foreach ($request->file('files', []) as $file) {
                $path = $file->store('procurement/'.$application->id, 'local');
                if ($path === false) {
                    throw new \RuntimeException('附件存储失败');
                }
                $paths[] = $path;
                ProcurementAttachment::create([
                    'application_id' => $application->id, 'uploaded_by' => $request->user()->id,
                    'kind' => 'other', 'original_name' => $file->getClientOriginalName(),
                    'disk' => 'local', 'path' => $path, 'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
        }
    }
}
