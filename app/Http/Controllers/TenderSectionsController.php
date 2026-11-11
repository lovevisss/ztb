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
    private const SPECIAL_METHODS = ['邀请招标', '竞争性谈判', '竞争性磋商', '单一来源', '询价', '谈判', '政采云等平台'];

    public function create(Request $request, TenderNumber $numbers): RedirectResponse
    {
        abort_if($request->user()->department_id === null, 422, '当前用户尚未关联部门');
        $section = $request->validate(['section' => ['required', Rule::in(['basic', 'items', 'engineering', 'budget', 'attachments'])]])['section'];
        $data = $this->sectionData($request, $section);
        $paths = [];
        $oldPaths = [];
        try {
            $application = DB::transaction(function () use ($request, $numbers, $section, $data, &$paths, &$oldPaths) {
                $application = ProcurementApplication::create([
                    'applicant_id' => $request->user()->id,
                    'department_id' => $request->user()->department_id,
                    'application_form' => 'central_tender',
                    'status' => 'draft',
                    'project_number' => $numbers->next(),
                ]);
                $this->saveSection($request, $application, $section, $data, $paths, $oldPaths);

                return $application;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }
        Storage::disk('local')->delete($oldPaths);
        $request->attributes->set('audit_subject', ['type' => 'application', 'id' => (string) $application->id,
            'label' => $application->project_number ?? $application->title]);

        return to_route('procurement.tenders.edit', ['application' => $application->id, 'tab' => $section === 'engineering' ? 'items' : $section]);
    }

    public function update(Request $request, ProcurementApplication $application): RedirectResponse
    {
        $this->authorizeDraft($request, $application);
        $section = $request->validate(['section' => ['required', Rule::in(['basic', 'items', 'engineering', 'budget', 'attachments'])]])['section'];
        $data = $this->sectionData($request, $section, $application);
        $paths = [];
        $oldPaths = [];
        try {
            DB::transaction(function () use ($request, $application, $section, $data, &$paths, &$oldPaths) {
                $locked = ProcurementApplication::query()->lockForUpdate()->findOrFail($application->id);
                $this->authorizeDraft($request, $locked);
                $this->saveSection($request, $locked, $section, $data, $paths, $oldPaths);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }
        Storage::disk('local')->delete($oldPaths);

        return to_route('procurement.tenders.edit', ['application' => $application->id, 'tab' => $section === 'engineering' ? 'items' : $section]);
    }

    public function submit(Request $request, ProcurementApplication $application, ApprovalWorkflow $workflow): RedirectResponse
    {
        $this->authorizeDraft($request, $application);
        DB::transaction(function () use ($request, $application, $workflow) {
            $application = ProcurementApplication::query()->lockForUpdate()->findOrFail($application->id);
            $this->authorizeDraft($request, $application);
            $errors = [];
            foreach (['title', 'category', 'contact_phone', 'procurement_requirements'] as $field) {
                if (blank($application->{$field})) {
                    $errors[$field] = '请填写此项。';
                }
            }
            if ($application->is_special_purchase && ! in_array($application->purchase_method, self::SPECIAL_METHODS, true)) {
                $errors['purchase_method'] = '请选择特殊采购方式。';
            }
            if ($application->has_usage_department && $application->usage_department_id === null) {
                $errors['usage_department_id'] = '请选择使用部门。';
            }
            if ($application->category === '工程类') {
                $engineering = DB::table('procurement_engineering_details')->where('application_id', $application->id)->first();
                foreach (['project_name', 'construction_content', 'project_overview', 'schedule_requirements'] as $field) {
                    if (blank($engineering?->{$field})) {
                        $errors[$field] = '请填写此项。';
                    }
                }
                $total = (int) ($engineering?->estimated_cost_cents ?? 0);
                if ($total <= 0) {
                    $errors['estimated_cost'] = '请填写有效的工程总造价。';
                }
                if (! ProcurementAttachment::query()->where('application_id', $application->id)->where('kind', 'engineering_bill')->exists()) {
                    $errors['bill_file'] = '请上传工程清单附件。';
                }
            } else {
                $items = DB::table('procurement_line_items')->where('application_id', $application->id);
                $total = (int) $items->sum('total_cents');
                if ($items->count() === 0 || $total <= 0) {
                    $errors['items'] = '请添加有效的采购清单。';
                }
            }
            if ((int) DB::table('procurement_budget_allocations')->where('application_id', $application->id)->sum('amount_cents') !== $total) {
                $errors['budget'] = '预算指标使用金额合计须与采购清单合计一致。';
            }
            $rule = ProcurementClassificationRule::effectiveFor($application->department_id);
            if ($total < $rule->threshold_cents) {
                $errors[$application->category === '工程类' ? 'estimated_cost' : 'items'] = '该金额应使用部门自行采购申请。';
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
        $itemId = DB::table('personal_catalog_items')->insertGetId([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'specification' => $data['specification'] ?? null,
            'unit' => $data['unit'] ?? null,
            'unit_price_cents' => (int) round($data['unit_price'] * 100),
            'remarks' => $data['remarks'] ?? null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $request->attributes->set('audit_subject', ['type' => 'catalog_item', 'id' => (string) $itemId,
            'label' => $data['name']]);

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
        $exitCode = Artisan::call('procurement:sync-budgets');

        if ($exitCode !== 0) {
            return back()->withErrors(['budget_sync' => '经费代码同步失败，本地数据未更改。请检查应用日志。']);
        }

        return back()->with('status', Artisan::output());
    }

    private function authorizeDraft(Request $request, ProcurementApplication $application): void
    {
        abort_unless($application->applicant_id === $request->user()->id, 403);
        abort_unless($application->application_form === 'central_tender' && in_array($application->status, ['draft', 'returned', 'withdrawn'], true), 403);
    }

    private function sectionData(Request $request, string $section, ?ProcurementApplication $application = null): array
    {
        return match ($section) {
            'basic' => $this->basicData($request),
            'items' => $request->validate([
                'items' => ['present', 'array', 'max:500'],
                'items.*.name' => ['required', 'string', 'max:255'],
                'items.*.specification' => ['nullable', 'string'],
                'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
                'items.*.unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
                'items.*.unit' => ['nullable', 'string', 'max:50'],
                'items.*.remarks' => ['nullable', 'string'],
            ]),
            'engineering' => $request->validate([
                'category' => ['required', Rule::in(['工程类'])],
                'project_name' => ['nullable', 'string', 'max:255'],
                'construction_content' => ['nullable', 'string'],
                'project_overview' => ['nullable', 'string'],
                'schedule_requirements' => ['nullable', 'string'],
                'estimated_cost' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
                'bill_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:20480'],
            ]),
            'budget' => $this->budgetData($request, $application),
            'attachments' => $request->validate([
                'files' => ['nullable', 'array', 'max:10'],
                'files.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:20480'],
                'delete_ids' => ['nullable', 'array'],
                'delete_ids.*' => ['required', 'integer', 'distinct', Rule::exists('procurement_attachments', 'id')
                    ->where('application_id', $application?->id ?? -1)->where('kind', 'other')],
            ]),
        };
    }

    private function budgetData(Request $request, ?ProcurementApplication $application): array
    {
        $data = $request->validate([
            'allocations' => ['present', 'array', 'max:100'],
            'allocations.*.budget_indicator_id' => ['required', 'integer', 'distinct', 'exists:budget_indicators,id'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'budget_basis_note' => ['nullable', 'string'],
        ]);
        foreach ($data['allocations'] as $index => $allocation) {
            $indicator = DB::table('budget_indicators')->find($allocation['budget_indicator_id']);
            $alreadySelected = $application !== null && DB::table('procurement_budget_allocations')
                ->where('application_id', $application->id)
                ->where('budget_indicator_id', $indicator->id)->exists();
            if (($indicator->source !== 'school_source' || $indicator->status !== '1') && ! $alreadySelected) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.budget_indicator_id" => '该经费来源不可新增选择，请选择启用的最新经费代码。',
                ]);
            }
        }

        return $data;
    }

    private function basicData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', Rule::in(['工程类', '货物类', '服务类'])],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'purchase_method' => ['nullable', 'string', 'max:255'],
            'is_special_purchase' => ['required', 'boolean'],
            'special_purchase_reason' => ['nullable', 'string'],
            'is_non_expenditure' => ['required', 'boolean'],
            'has_usage_department' => ['required', 'boolean'],
            'usage_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'procurement_requirements' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
        ]);
        if ($data['is_special_purchase']) {
            if (! in_array($data['purchase_method'] ?? null, self::SPECIAL_METHODS, true)) {
                throw ValidationException::withMessages(['purchase_method' => '请选择特殊采购方式。']);
            }
        } else {
            $data['purchase_method'] = '公开招标';
            $data['special_purchase_reason'] = null;
        }

        return $data;
    }

    private function saveSection(Request $request, ProcurementApplication $application, string $section, array $data, array &$paths, array &$oldPaths): void
    {
        if ($section === 'basic') {
            if (! $data['has_usage_department']) {
                $data['usage_department_id'] = null;
            }
            $application->update($data);
            $this->refreshActiveBudget($application);
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
            $this->refreshActiveBudget($application);
        } elseif ($section === 'engineering') {
            $application->update(['category' => '工程类']);
            DB::table('procurement_engineering_details')->updateOrInsert(
                ['application_id' => $application->id],
                [
                    'project_name' => $data['project_name'] ?? null,
                    'construction_content' => $data['construction_content'] ?? null,
                    'project_overview' => $data['project_overview'] ?? null,
                    'schedule_requirements' => $data['schedule_requirements'] ?? null,
                    'estimated_cost_cents' => isset($data['estimated_cost']) ? (int) round($data['estimated_cost'] * 100) : null,
                    'created_at' => now(), 'updated_at' => now(),
                ],
            );
            if ($request->hasFile('bill_file')) {
                $file = $request->file('bill_file');
                $path = $file->store('procurement/'.$application->id, 'local');
                if ($path === false) {
                    throw new \RuntimeException('工程清单附件存储失败');
                }
                $paths[] = $path;
                $oldFiles = ProcurementAttachment::query()->where('application_id', $application->id)->where('kind', 'engineering_bill')->get();
                foreach ($oldFiles as $oldFile) {
                    $oldPaths[] = $oldFile->path;
                    $oldFile->delete();
                }
                ProcurementAttachment::create([
                    'application_id' => $application->id, 'uploaded_by' => $request->user()->id,
                    'kind' => 'engineering_bill', 'original_name' => $file->getClientOriginalName(),
                    'disk' => 'local', 'path' => $path, 'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
            $this->refreshActiveBudget($application);
        } elseif ($section === 'budget') {
            $previous = DB::table('procurement_budget_allocations')->where('application_id', $application->id)
                ->get()->keyBy('budget_indicator_id');
            DB::table('procurement_budget_allocations')->where('application_id', $application->id)->delete();
            foreach ($data['allocations'] as $allocation) {
                $indicator = DB::table('budget_indicators')->find($allocation['budget_indicator_id']);
                $snapshot = $previous->get($indicator->id);
                DB::table('procurement_budget_allocations')->insert([
                    'application_id' => $application->id, 'budget_indicator_id' => $indicator->id,
                    'code' => $snapshot?->code ?? $indicator->code,
                    'name' => $snapshot?->name ?? $indicator->name,
                    'director' => $snapshot?->director ?? $indicator->director,
                    'amount_cents' => (int) round($allocation['amount'] * 100),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $application->update(['budget_basis_note' => $data['budget_basis_note'] ?? null]);
        } else {
            $filesToDelete = ProcurementAttachment::query()->where('application_id', $application->id)
                ->where('kind', 'other')->whereIn('id', $data['delete_ids'] ?? [])->get();
            foreach ($filesToDelete as $existingFile) {
                $oldPaths[] = $existingFile->path;
                $existingFile->delete();
            }
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

    private function refreshActiveBudget(ProcurementApplication $application): void
    {
        $total = $application->category === '工程类'
            ? DB::table('procurement_engineering_details')->where('application_id', $application->id)->value('estimated_cost_cents')
            : DB::table('procurement_line_items')->where('application_id', $application->id)->sum('total_cents');
        $application->update(['budget_cents' => $total]);
    }
}
