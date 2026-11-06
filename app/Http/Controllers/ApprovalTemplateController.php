<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeManager($request);

        return Inertia::render('procurement/ApprovalTemplates', [
            'templates' => DB::table('approval_templates')->orderByDesc('id')->get()->map(function ($template) {
                $template->steps = DB::table('approval_template_steps')->where('template_id', $template->id)->orderBy('position')->get();

                return $template;
            }),
            'departments' => DB::table('departments')->orderBy('name')->get(['id', 'name']),
            'users' => DB::table('users')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'department_id']),
            'roles' => DB::table('roles')->whereIn('code', ['department_head', 'procurement_manager', 'responsible_department', 'finance', 'school_leader'])->get(['code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManager($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'purchase_type' => ['required', Rule::in(['self_purchase', 'central_tender'])],
            'min_budget_yuan' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'max_budget_yuan' => ['nullable', 'numeric', 'gt:min_budget_yuan', 'decimal:0,2'],
            'applicant_department_id' => ['nullable', 'exists:departments,id'],
            'responsible_department' => ['nullable', 'string', 'max:255'],
            'steps' => ['required', 'array', 'min:1', 'max:10'],
            'steps.*.role_code' => ['required', 'exists:roles,code'],
            'steps.*.assignee_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
        ]);

        $hasProcurementReview = false;
        foreach ($data['steps'] as $step) {
            if ($step['role_code'] === 'procurement_manager') {
                $hasProcurementReview = true;
                break;
            }
        }
        if (! $hasProcurementReview) {
            throw ValidationException::withMessages(['steps' => '审批流程必须包含采购管理部门审核节点。']);
        }

        DB::transaction(function () use ($request, $data) {
            $version = (int) DB::table('approval_templates')->where('purchase_type', $data['purchase_type'])->max('version') + 1;
            $templateId = DB::table('approval_templates')->insertGetId([
                'name' => $data['name'],
                'purchase_type' => $data['purchase_type'],
                'min_budget_cents' => (int) round((float) $data['min_budget_yuan'] * 100),
                'max_budget_cents' => isset($data['max_budget_yuan']) ? (int) round((float) $data['max_budget_yuan'] * 100) : null,
                'applicant_department_id' => $data['applicant_department_id'] ?? null,
                'responsible_department' => $data['responsible_department'] ?? null,
                'version' => $version,
                'is_active' => true,
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($data['steps'] as $index => $step) {
                DB::table('approval_template_steps')->insert([
                    'template_id' => $templateId,
                    'position' => $index + 1,
                    'role_code' => $step['role_code'],
                    'assignee_id' => $step['assignee_id'] ?? null,
                ]);
            }
        });

        return to_route('procurement.approval-templates.index');
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole(['procurement_manager', 'system_admin']), 403);
    }
}
