<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalAssigneeSettingsController extends Controller
{
    private const ROLES = [
        'procurement_head' => '采购管理部门领导',
        'procurement_manager' => '采购管理人员',
        'asset_head' => '资产管理部领导',
        'asset_manager' => '资产管理员',
        'finance' => '计划财务部审批人',
        'tender_group_representative' => '招投标领导小组代表',
    ];

    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(['system_admin']), 403);

        $matches = DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->join('users', 'users.id', '=', 'role_user.user_id')
            ->whereIn('roles.code', array_keys(self::ROLES))->where('users.is_active', true)
            ->get(['roles.code', 'users.id', 'users.name'])->groupBy('code');
        $defaults = DB::table('approval_role_defaults')->pluck('user_id', 'role_code');
        $defaultUsers = DB::table('users')->whereIn('id', $defaults->values())->get(['id', 'name', 'is_active'])->keyBy('id');
        $users = DB::table('users')->leftJoin('departments', 'departments.id', '=', 'users.department_id')
            ->where('users.is_active', true)->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.school_account', 'departments.name as department_name']);
        $legacyRepresentatives = DB::table('procurement_department_approvers')
            ->whereNotNull('tender_group_representative_id')->distinct()->pluck('tender_group_representative_id');
        $representativeNotice = match (true) {
            $defaults->has('tender_group_representative') => null,
            $legacyRepresentatives->count() > 1 => '历史部门配置中的小组代表不一致，请指定一名全局代表。',
            $legacyRepresentatives->count() === 1 => '历史小组代表账号已停用，请指定一名有效的全局代表。',
            default => '尚未配置全局小组代表，请指定一名有效人员。',
        };

        return Inertia::render('admin/ApprovalAssignees', [
            'roles' => collect(self::ROLES)->map(function ($name, $code) use ($matches, $defaults, $defaultUsers) {
                $assigned = $matches->get($code, collect());
                $defaultId = $defaults->get($code);
                $defaultUser = $defaultUsers->get($defaultId);

                return [
                    'code' => $code, 'name' => $name, 'default_user_id' => $defaultId,
                    'required_default' => $code === 'tender_group_representative',
                    'default_user_name' => $defaultUser?->name,
                    'default_user_active' => $defaultUser === null ? null : (bool) $defaultUser->is_active,
                    'role_users' => $assigned->pluck('name')->all(),
                    'effective_user_name' => $defaultId !== null
                        ? ($defaultUser?->is_active ? $defaultUser->name : null)
                        : ($assigned->count() === 1 ? $assigned->first()->name : null),
                ];
            })->values(),
            'users' => $users,
            'departments' => DB::table('departments')->orderBy('name')->get(['id', 'name']),
            'departmentApprovers' => DB::table('procurement_department_approvers')->get(['department_id', 'supervising_leader_id']),
            'representativeNotice' => $representativeNotice,
            'budgetIndicatorSummary' => [
                'total' => DB::table('budget_indicators')->where('source', 'school_source')->count(),
                'unresolved' => DB::table('budget_indicators')->where('source', 'school_source')
                    ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('users')
                        ->whereColumn('users.school_account', 'budget_indicators.charge_sno')
                        ->where('users.is_active', true))->count(),
            ],
        ]);
    }

    public function update(Request $request, string $role): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['system_admin']), 403);
        abort_unless(array_key_exists($role, self::ROLES), 404);
        $data = $request->validate([
            'user_id' => [$role === 'tender_group_representative' ? 'required' : 'present',
                $role === 'tender_group_representative' ? 'integer' : 'nullable',
                Rule::exists('users', 'id')->where('is_active', true)],
        ]);

        DB::transaction(function () use ($request, $role, $data): void {
            if ($data['user_id'] === null) {
                DB::table('approval_role_defaults')->where('role_code', $role)->delete();

                return;
            }
            DB::table('approval_role_defaults')->updateOrInsert(
                ['role_code' => $role],
                ['user_id' => $data['user_id'], 'updated_by' => $request->user()->id,
                    'created_at' => DB::table('approval_role_defaults')->where('role_code', $role)->value('created_at') ?? now(),
                    'updated_at' => now()],
            );
        });
        $request->attributes->set('audit_subject', ['type' => 'approval_role', 'id' => $role, 'label' => self::ROLES[$role]]);

        return back()->with('status', $data['user_id'] === null ? '已恢复按角色自动匹配。' : '岗位默认审批人已保存。');
    }
}
