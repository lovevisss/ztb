<?php

namespace App\Http\Controllers;

use App\Models\ProcurementApplication;
use App\Models\ProcurementAttachment;
use App\Models\ProcurementClassificationRule;
use App\Services\ApprovalWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CentralTenderApplicationController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('procurement/TenderPolicy');
    }

    public function newForm(Request $request): Response
    {
        return $this->form($request);
    }

    public function edit(Request $request, ProcurementApplication $application): Response
    {
        $this->authorizeDraft($request, $application);

        return $this->form($request, $application);
    }

    public function store(Request $request, ApprovalWorkflow $workflow): RedirectResponse
    {
        abort_if($request->user()->department_id === null, 422, '当前用户尚未关联部门');

        $data = $this->validatedData($request);
        $this->validateSubmission($request, $data, $request->user()->department_id);

        $storedPaths = [];
        try {
            DB::transaction(function () use ($request, $data, $workflow, &$storedPaths) {
                $application = ProcurementApplication::create([
                    ...$data,
                    'application_form' => 'central_tender',
                    'applicant_id' => $request->user()->id,
                    'department_id' => $request->user()->department_id,
                ]);
                $this->storeAttachments($request, $application, $storedPaths);
                $this->submitIfRequested($request, $application, $workflow);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }

        return to_route('procurement.applications.index');
    }

    public function update(Request $request, ProcurementApplication $application, ApprovalWorkflow $workflow): RedirectResponse
    {
        $this->authorizeDraft($request, $application);

        $data = $this->validatedData($request);
        $this->validateSubmission($request, $data, $application->department_id, $application);

        $storedPaths = [];
        try {
            DB::transaction(function () use ($request, $application, $data, $workflow, &$storedPaths) {
                $application->update($data);
                $this->storeAttachments($request, $application, $storedPaths);
                $this->submitIfRequested($request, $application, $workflow);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }

        return to_route('procurement.applications.index');
    }

    private function form(Request $request, ?ProcurementApplication $application = null): Response
    {
        return Inertia::render('procurement/TenderForm', [
            'application' => $application,
            'attachments' => $application === null ? [] : ProcurementAttachment::query()
                ->where('application_id', $application->id)->where('kind', '!=', 'engineering_bill')
                ->get(['id', 'kind', 'original_name', 'size', 'mime_type']),
            'departmentAssigned' => $request->user()->department_id !== null,
            'threshold' => ProcurementClassificationRule::effectiveFor($request->user()->department_id)->threshold_cents / 100,
            'applicantName' => $request->user()->name,
            'applicantPhone' => $request->user()->mobile_phone,
            'departmentName' => DB::table('departments')->where('id', $request->user()->department_id)->value('name'),
            'departments' => DB::table('departments')->orderBy('name')->get(['id', 'name']),
            'items' => $application === null ? [] : DB::table('procurement_line_items')->where('application_id', $application->id)->orderBy('position')->get(),
            'engineering' => $application === null ? null : DB::table('procurement_engineering_details')->where('application_id', $application->id)->first(),
            'engineeringBill' => $application === null ? null : ProcurementAttachment::query()
                ->where('application_id', $application->id)->where('kind', 'engineering_bill')
                ->first(['id', 'original_name', 'size', 'mime_type']),
            'allocations' => $application === null ? [] : DB::table('procurement_budget_allocations')->where('application_id', $application->id)->get(),
            'catalog' => DB::table('personal_catalog_items')->where('user_id', $request->user()->id)->orderBy('name')->get(),
            'canSyncBudgets' => $request->user()->hasAnyRole(['procurement_manager', 'system_admin']),
        ]);
    }

    private function authorizeDraft(Request $request, ProcurementApplication $application): void
    {
        abort_unless($application->applicant_id === $request->user()->id, 403);
        abort_unless(in_array($application->status, ['draft', 'returned'], true) && $application->application_form === 'central_tender', 403);
    }

    /** @return array<string, string|int|bool|null> */
    private function validatedData(Request $request): array
    {
        $intent = $request->validate(['intent' => ['required', 'in:draft,submit']])['intent'];
        $required = $intent === 'submit' ? 'required' : 'nullable';

        $data = $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'project_leader' => [$required, 'string', 'max:255'],
            'budget' => [$required, 'numeric', 'min:0.01', 'decimal:0,2'],
            'funding_source' => [$required, 'string', 'max:255'],
            'organization_form' => [$required, 'string', 'max:255'],
            'purchase_method' => [$required, 'string', 'max:255'],
            'responsible_department' => [$required, 'string', 'max:255'],
            'procurement_requirements' => [$required, 'string'],
            'purchase_list' => [$required, 'string'],
            'technical_parameters' => [$required, 'string'],
            'service_requirements' => [$required, 'string'],
            'acceptance_criteria' => [$required, 'string'],
            'budget_basis_note' => [$required, 'string'],
            'demand_document' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:20480'],
            'budget_basis_document' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:20480'],
            'other_documents' => ['nullable', 'array', 'max:10'],
            'other_documents.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:20480'],
        ]);

        $data['budget_cents'] = isset($data['budget']) ? (int) round((float) $data['budget'] * 100) : null;
        unset($data['budget'], $data['demand_document'], $data['budget_basis_document'], $data['other_documents']);

        return $data;
    }

    /** @param array<string, string|int|bool|null> $data */
    private function validateSubmission(Request $request, array $data, int $departmentId, ?ProcurementApplication $application = null): void
    {
        if ($request->input('intent') !== 'submit') {
            return;
        }

        $rule = ProcurementClassificationRule::effectiveFor($departmentId);
        if ($data['budget_cents'] < $rule->threshold_cents) {
            throw ValidationException::withMessages(['budget' => '该预算应使用部门自行采购申请。']);
        }

        $missingDocuments = [];
        foreach (['demand_document', 'budget_basis_document'] as $kind) {
            $existing = $application !== null && ProcurementAttachment::query()
                ->where('application_id', $application->id)->where('kind', $kind)->exists();
            if (! $existing && ! $request->hasFile($kind)) {
                $missingDocuments[$kind] = '提交统一招标申请时必须上传此材料。';
            }
        }
        if ($missingDocuments !== []) {
            throw ValidationException::withMessages($missingDocuments);
        }
    }

    /** @param list<string> $storedPaths */
    private function storeAttachments(Request $request, ProcurementApplication $application, array &$storedPaths): void
    {
        foreach (['demand_document', 'budget_basis_document'] as $kind) {
            if ($request->hasFile($kind)) {
                $this->storeAttachment($request->file($kind), $kind, $request, $application, $storedPaths);
            }
        }

        foreach ($request->file('other_documents', []) as $file) {
            $this->storeAttachment($file, 'other', $request, $application, $storedPaths);
        }
    }

    /** @param list<string> $storedPaths */
    private function storeAttachment(UploadedFile $file, string $kind, Request $request, ProcurementApplication $application, array &$storedPaths): void
    {
        $path = $file->store('procurement/'.$application->id, 'local');
        if ($path === false) {
            throw new \RuntimeException('采购附件存储失败');
        }
        $storedPaths[] = $path;

        ProcurementAttachment::create([
            'application_id' => $application->id,
            'uploaded_by' => $request->user()->id,
            'kind' => $kind,
            'original_name' => $file->getClientOriginalName(),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    private function submitIfRequested(Request $request, ProcurementApplication $application, ApprovalWorkflow $workflow): void
    {
        if ($request->input('intent') !== 'submit') {
            return;
        }

        $rule = ProcurementClassificationRule::effectiveFor($application->department_id);
        $application->update([
            'status' => 'submitted',
            'purchase_type' => 'central_tender',
            'classification_rule_id' => $rule->id,
            'classification_threshold_cents' => $rule->threshold_cents,
            'project_number' => $application->project_number ?? 'CG'.now()->format('Y').'-'.str_pad((string) $application->id, 6, '0', STR_PAD_LEFT),
            'submitted_at' => now(),
        ]);

        $workflow->start($application);
    }
}
