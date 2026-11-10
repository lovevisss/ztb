<?php

namespace App\Http\Controllers;

use App\Models\ProcurementApplication;
use App\Models\ProcurementClassificationRule;
use App\Services\ApprovalWorkflow;
use App\Services\LegacyApprovalTimeline;
use App\Services\MyProcurementApplications;
use App\Services\ProcurementSpreadsheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProcurementApplicationController extends Controller
{
    public function index(Request $request, MyProcurementApplications $listing, LegacyApprovalTimeline $timeline): Response
    {
        $account = $request->user()->school_account;
        if ($request->filled('legacy')) {
            $sourceId = $request->validate(['legacy' => ['required', 'string', 'max:64']])['legacy'];
            $project = DB::table('legacy_procurement_records')
                ->where('source_table', 'zcy_cgsq')
                ->where('source_id', $sourceId)
                ->where('creator_account', $account ?? '')
                ->first();
            abort_if($project === null, 404);

            $records = DB::table('legacy_procurement_records')
                ->where('application_source_id', $sourceId)
                ->where('source_table', '!=', 'zcy_cgsq')
                ->orderBy('occurred_at')->orderBy('source_id')
                ->get(['source_table', 'source_id', 'number', 'title', 'status', 'amount_cents', 'occurred_at', 'details']);

            return Inertia::render('procurement/LegacyDetail', [
                'project' => $project,
                'records' => $records,
                'workflowNodes' => $timeline->nodes($project, $records),
                'attachments' => DB::table('legacy_procurement_attachments')
                    ->where('application_source_id', $sourceId)
                    ->get(['owner_table', 'owner_source_id', 'name', 'migration_status']),
                'backHref' => '/procurement/applications',
            ]);
        }

        $filters = $this->listFilters($request);
        $records = $listing->records($request->user());
        $filtered = $listing->filter($records, $filters);
        $page = min((int) ($filters['page'] ?? 1), max(1, (int) ceil($filtered->count() / 15)));

        return Inertia::render('procurement/Index', [
            'records' => $filtered->slice(($page - 1) * 15, 15)->values(),
            'total' => $filtered->count(),
            'page' => $page,
            'lastPage' => max(1, (int) ceil($filtered->count() / 15)),
            'counts' => [
                'draft' => $records->where('tab', 'draft')->count(),
                'submitted' => $records->where('tab', 'submitted')->count(),
            ],
            'years' => $records->pluck('year')->filter(fn ($year) => $year !== null)
                ->unique()->sortDesc()->values(),
            'filters' => $filters,
        ]);
    }

    public function export(Request $request, MyProcurementApplications $listing, ProcurementSpreadsheet $spreadsheet): BinaryFileResponse
    {
        $filters = $this->listFilters($request);
        $records = $listing->filter($listing->records($request->user()), $filters);
        $path = $spreadsheet->writeTable(
            ['项目编号', '项目名称', '申报部门', '申请金额（元）', '提交日期', '申请人', '申请人电话', '流程状态', '来源', '所属年度', '常规项目'],
            $records->map(fn (array $item) => [
                $item['number'], $item['title'], $item['department_name'],
                $item['amount_cents'] === null ? null : $item['amount_cents'] / 100,
                $item['submitted_at'] === null ? null : substr((string) $item['submitted_at'], 0, 10),
                $item['applicant_name'], $item['contact_phone'], $item['status'], $item['source'],
                $item['year'], $item['is_regular'] ? '是（匹配'.implode('、', $item['matching_years']).'年）' : '否',
            ]),
            '我的采购申请',
        );

        return response()->download($path, '我的采购申请-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /** @return array<string, mixed> */
    private function listFilters(Request $request): array
    {
        return $request->validate([
            'tab' => ['nullable', 'in:draft,submitted'],
            'number' => ['nullable', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:100'],
            'department_name' => ['nullable', 'string', 'max:100'],
            'applicant_name' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'between:1900,2100'],
            'regular' => ['nullable', 'in:all,regular,other'],
            'page' => ['nullable', 'integer', 'min:1'],
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
