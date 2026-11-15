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

        $resolved = [];
        if ($application->application_form === 'central_tender' && $application->has_usage_department && $application->usage_department_id !== null) {
            $resolved[] = ['role_code' => 'usage_department_head', 'assignee_id' => $this->departmentHead($application->usage_department_id, '使用部门')];
        }
        $resolved[] = ['role_code' => 'department_head', 'assignee_id' => $this->departmentHead($application->department_id, '申请部门')];

        $allocations = DB::table('procurement_budget_allocations')->where('application_id', $application->id)
            ->orderBy('id')->get(['name', 'charge_sno']);
        if ($allocations->isEmpty()) {
            throw ValidationException::withMessages(['allocations' => '请先填写经费代码及金额明细。']);
        }
        foreach ($allocations as $allocation) {
            $account = trim((string) $allocation->charge_sno);
            $userId = $account === '' ? null : DB::table('users')->where('school_account', $account)
                ->where('is_active', true)->value('id');
            if ($userId === null) {
                throw ValidationException::withMessages(['allocations' => "经费「{$allocation->name}」的负责人缺少有效工号或系统账号。"]);
            }
            $resolved[] = ['role_code' => 'budget_director', 'assignee_id' => (int) $userId];
        }
        foreach (['procurement_head', 'procurement_manager', 'asset_head'] as $role) {
            $configured = DB::table('approval_template_steps')->where('template_id', $template->id)
                ->where('role_code', $role)->value('assignee_id');
            if ($configured !== null && ! DB::table('users')->where('id', $configured)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['approvers' => '审批岗位'.$this->roleName($role).'的流程指定账号已停用。']);
            }
            $default = $configured === null
                ? DB::table('approval_role_defaults')->where('role_code', $role)->value('user_id') : null;
            if ($default !== null && ! DB::table('users')->where('id', $default)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['approvers' => '审批岗位'.$this->roleName($role).'的默认审批人账号已停用。']);
            }
            $resolved[] = ['role_code' => $role, 'assignee_id' => $configured !== null
                ? (int) $configured : ($default !== null ? (int) $default : $this->roleUser($role))];
        }
        $mapping = DB::table('procurement_department_approvers')->where('department_id', $application->department_id)->first();
        if ($mapping === null) {
            throw ValidationException::withMessages(['department_id' => '申请人所在部门尚未配置经费分管院领导及招投标领导小组代表。']);
        }
        foreach (['supervising_leader_id' => 'supervising_leader', 'tender_group_representative_id' => 'tender_group_representative'] as $field => $role) {
            $id = $mapping->{$field};
            if (! DB::table('users')->where('id', $id)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['department_id' => '部门审批映射中存在无效账号，请联系管理员。']);
            }
            $resolved[] = ['role_code' => $role, 'assignee_id' => (int) $id];
        }

        $seen = [];
        $skipped = [];
        $resolved = array_values(array_filter($resolved, function (array $step) use (&$seen, &$skipped) {
            if (isset($seen[$step['assignee_id']])) {
                $skipped[] = $step['role_code'].'：与 '.$seen[$step['assignee_id']].' 为同一审批人，已跳过';
                return false;
            }
            $seen[$step['assignee_id']] = $step['role_code'];
            return true;
        }));

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
        foreach ($skipped as $detail) {
            DB::table('approval_events')->insert([
                'application_id' => $application->id, 'actor_id' => $application->applicant_id,
                'event' => 'duplicate_skipped', 'details' => $detail,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function departmentHead(int $departmentId, string $kind): int
    {
        $name = DB::table('departments')->where('id', $departmentId)->value('name') ?? (string) $departmentId;
        $manual = DB::table('department_head_assignments')->where('department_id', $departmentId)->value('user_id');
        if ($manual !== null) {
            if (! DB::table('users')->where('id', $manual)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['approvers' => "{$kind}「{$name}」手动指定的负责人账号已停用，请联系管理员。"]);
            }
            return (int) $manual;
        }

        $id = DB::table('users')->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.code', 'department_head')->where('users.is_active', true)
            ->where('users.department_id', $departmentId)->min('users.id');
        if ($id === null) {
            throw ValidationException::withMessages(['approvers' => "{$kind}「{$name}」尚未配置有效负责人，请联系管理员在“部门负责人”中设置。"]);
        }
        return (int) $id;
    }

    private function roleUser(string $roleCode, ?int $departmentId = null): int
    {
        $query = DB::table('users')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.code', $roleCode)
            ->where('users.is_active', true);

        if ($departmentId !== null) {
            $query->where('users.department_id', $departmentId);
        }
        $ids = $query->distinct()->pluck('users.id');
        if ($roleCode === 'department_head' && $ids->isNotEmpty()) {
            return (int) $ids->min();
        }
        if ($ids->count() !== 1) {
            $reason = $ids->isEmpty() ? '尚无有效人员' : '匹配到多名有效人员';
            throw ValidationException::withMessages(['approvers' => '审批岗位'.$this->roleName($roleCode)."{$reason}，请联系管理员配置默认审批人。"]);
        }
        return (int) $ids->first();
    }

    private function roleName(string $roleCode): string
    {
        return match ($roleCode) {
            'procurement_head' => '采购管理部门领导',
            'procurement_manager' => '采购管理人员',
            'asset_head' => '资产管理部领导',
            default => $roleCode,
        };
    }
}
