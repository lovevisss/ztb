<?php

namespace App\Services;

use App\Models\ProcurementApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalWorkflow
{
    public function start(ProcurementApplication $application): void
    {
        $template = DB::table('approval_templates')
            ->where('is_active', true)
            ->where('purchase_type', $application->purchase_type)
            ->where('min_budget_cents', '<=', $application->budget_cents)
            ->where(function ($query) use ($application) {
                $query->whereNull('max_budget_cents')->orWhere('max_budget_cents', '>', $application->budget_cents);
            })
            ->where(function ($query) use ($application) {
                $query->whereNull('applicant_department_id')->orWhere('applicant_department_id', $application->department_id);
            })
            ->where(function ($query) use ($application) {
                $query->whereNull('responsible_department')->orWhere('responsible_department', $application->responsible_department);
            })
            ->orderByRaw('CASE WHEN applicant_department_id IS NOT NULL THEN 1 ELSE 0 END + CASE WHEN responsible_department IS NOT NULL THEN 1 ELSE 0 END DESC')
            ->orderByDesc('version')
            ->first();

        if ($template === null) {
            throw ValidationException::withMessages(['budget' => '当前项目没有可用的审批流程，请联系采购管理部门。']);
        }

        $templateSteps = DB::table('approval_template_steps')->where('template_id', $template->id)->orderBy('position')->get();
        if ($templateSteps->isEmpty()) {
            throw ValidationException::withMessages(['budget' => '审批流程尚未配置审批节点。']);
        }

        $resolved = [];
        foreach ($templateSteps as $step) {
            $assigneeId = $step->assignee_id ?? $this->resolveAssignee($step->role_code, $application);
            if ($assigneeId === null || ! DB::table('users')->where('id', $assigneeId)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['budget' => "审批岗位 {$step->role_code} 尚无匹配人员，请联系管理员。"]);
            }
            $resolved[] = ['position' => $step->position, 'role_code' => $step->role_code, 'assignee_id' => $assigneeId];
        }

        if ($application->application_form === 'central_tender' && $application->has_usage_department && $application->usage_department_id !== null) {
            $head = DB::table('users')->join('role_user', 'role_user.user_id', '=', 'users.id')
                ->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->where('roles.code', 'department_head')->where('users.department_id', $application->usage_department_id)
                ->where('users.is_active', true)->orderBy('users.id')->value('users.id');
            if ($head === null) {
                throw ValidationException::withMessages(['usage_department_id' => '使用部门尚无有效负责人，请联系管理员。']);
            }
            if ((int) $head !== $resolved[0]['assignee_id']) {
                array_unshift($resolved, ['position' => 0, 'role_code' => 'usage_department_head', 'assignee_id' => (int) $head]);
            }
        }

        $attempt = $application->approval_attempt + 1;
        $application->update([
            'status' => 'submitted',
            'approval_template_id' => $template->id,
            'approval_attempt' => $attempt,
            'submitted_at' => now(),
        ]);

        foreach ($resolved as $index => $step) {
            DB::table('approval_steps')->insert([
                'application_id' => $application->id,
                'template_id' => $template->id,
                'attempt' => $attempt,
                'position' => $index + 1,
                'role_code' => $step['role_code'],
                'assignee_id' => $step['assignee_id'],
                'status' => $index === 0 ? 'pending' : 'queued',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('approval_events')->insert([
            'application_id' => $application->id,
            'actor_id' => $application->applicant_id,
            'event' => $attempt === 1 ? 'submitted' : 'resubmitted',
            'details' => "第 {$attempt} 轮审批，流程版本 {$template->version}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function resolveAssignee(string $roleCode, ProcurementApplication $application): ?int
    {
        $query = DB::table('users')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.code', $roleCode)
            ->where('users.is_active', true);

        if ($roleCode === 'department_head') {
            $query->where('users.department_id', $application->department_id);
        } elseif ($roleCode === 'responsible_department') {
            $query->join('departments', 'departments.id', '=', 'users.department_id')
                ->where('departments.name', $application->responsible_department);
        }

        $id = $query->orderBy('users.id')->value('users.id');

        return $id === null ? null : (int) $id;
    }
}
