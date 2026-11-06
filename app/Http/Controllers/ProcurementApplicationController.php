<?php

namespace App\Http\Controllers;

use App\Models\ProcurementApplication;
use App\Models\ProcurementClassificationRule;
use App\Services\ApprovalWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementApplicationController extends Controller
{
    public function index(Request $request): Response
    {
        $account = $request->user()->school_account;

        return Inertia::render('procurement/Index', [
            'applications' => ProcurementApplication::query()
                ->where('applicant_id', $request->user()->id)
                ->latest()->get(),
            'legacyApplications' => $account === null ? [] : DB::table('legacy_procurement_records')
                ->where('source_table', 'zcy_cgsq')
                ->where('creator_account', $account)
                ->orderByDesc('occurred_at')
                ->get(['source_id', 'number', 'title', 'amount_cents', 'status', 'occurred_at']),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('procurement/Form', [
            'application' => null,
            'departmentAssigned' => $request->user()->department_id !== null,
            'threshold' => ProcurementClassificationRule::effectiveFor($request->user()->department_id)->threshold_cents / 100,
        ]);
    }

    public function edit(Request $request, ProcurementApplication $application): Response
    {
        abort_unless($application->applicant_id === $request->user()->id, 403);
        abort_unless(in_array($application->status, ['draft', 'returned'], true) && $application->application_form === 'self_purchase', 403);

        return Inertia::render('procurement/Form', [
            'application' => $application,
            'departmentAssigned' => $request->user()->department_id !== null,
            'threshold' => ProcurementClassificationRule::effectiveFor($request->user()->department_id)->threshold_cents / 100,
        ]);
    }

    public function store(Request $request, ApprovalWorkflow $workflow): RedirectResponse
    {
        abort_if($request->user()->department_id === null, 422, '当前用户尚未关联部门');

        $data = $this->validatedData($request);
        $this->validateSelfPurchaseBudget($request, $data, $request->user()->department_id);
        DB::transaction(function () use ($request, $data, $workflow) {
            $application = ProcurementApplication::create([
                ...$data,
                'application_form' => 'self_purchase',
                'applicant_id' => $request->user()->id,
                'department_id' => $request->user()->department_id,
            ]);

            $this->submitIfRequested($application, $request, $workflow);
        });

        return to_route('procurement.applications.index');
    }

    public function update(Request $request, ProcurementApplication $application, ApprovalWorkflow $workflow): RedirectResponse
    {
        abort_unless($application->applicant_id === $request->user()->id, 403);
        abort_unless(in_array($application->status, ['draft', 'returned'], true) && $application->application_form === 'self_purchase', 403);

        $data = $this->validatedData($request);
        $this->validateSelfPurchaseBudget($request, $data, $application->department_id);
        DB::transaction(function () use ($application, $data, $request, $workflow) {
            $application->update($data);
            $this->submitIfRequested($application, $request, $workflow);
        });

        return to_route('procurement.applications.index');
    }

    /** @return array<string, string|int|bool|null> */
    private function validatedData(Request $request): array
    {
        $intent = $request->validate(['intent' => ['required', 'in:draft,submit']])['intent'];
        $required = $intent === 'submit' ? 'required' : 'nullable';

        $data = $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'budget' => [$required, 'numeric', 'min:0.01', 'decimal:0,2'],
            'funding_source' => [$required, 'string', 'max:255'],
            'organization_form' => [$required, 'string', 'max:255'],
            'purchase_method' => [$required, 'string', 'max:255'],
            'reason' => [$required, 'string'],
            'responsible_department' => [$required, 'string', 'max:255'],
            'is_fixed_asset' => [$required, 'boolean'],
        ]);

        $data['budget_cents'] = isset($data['budget']) ? (int) round((float) $data['budget'] * 100) : null;
        unset($data['budget']);

        return $data;
    }

    private function submitIfRequested(ProcurementApplication $application, Request $request, ApprovalWorkflow $workflow): void
    {
        if ($request->input('intent') !== 'submit') {
            return;
        }

        $rule = ProcurementClassificationRule::effectiveFor($application->department_id);

        $application->update([
            'status' => 'submitted',
            'purchase_type' => $application->budget_cents < $rule->threshold_cents ? 'self_purchase' : 'central_tender',
            'classification_rule_id' => $rule->id,
            'classification_threshold_cents' => $rule->threshold_cents,
            'project_number' => $application->project_number ?? 'CG'.now()->format('Y').'-'.str_pad((string) $application->id, 6, '0', STR_PAD_LEFT),
            'submitted_at' => now(),
        ]);

        $workflow->start($application);
    }

    /** @param array<string, string|int|bool|null> $data */
    private function validateSelfPurchaseBudget(Request $request, array $data, int $departmentId): void
    {
        if ($request->input('intent') !== 'submit') {
            return;
        }

        $rule = ProcurementClassificationRule::effectiveFor($departmentId);
        if ($data['budget_cents'] >= $rule->threshold_cents) {
            throw ValidationException::withMessages(['budget' => '该预算应使用统一招标申请。']);
        }
    }
}
