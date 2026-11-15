<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentHeadController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(['system_admin']), 403);
        $search = trim($request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? '');
        $departments = DB::table('departments')
            ->leftJoin('department_head_assignments as manual', 'manual.department_id', '=', 'departments.id')
            ->leftJoin('users as selected', 'selected.id', '=', 'manual.user_id')
            ->when($search !== '', fn ($query) => $query->where('departments.name', 'like', '%'.$search.'%'))
            ->orderBy('departments.name')
            ->get(['departments.id', 'departments.name', 'departments.is_active',
                'manual.user_id as manual_user_id', 'selected.name as manual_user_name',
                'selected.is_active as manual_user_active']);
        $fallback = DB::table('users')->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.code', 'department_head')->where('users.is_active', true)
            ->whereIn('users.department_id', $departments->pluck('id'))
            ->orderBy('users.id')->get(['users.id', 'users.name', 'users.department_id'])
            ->groupBy('department_id');
        $departments->each(function ($department) use ($fallback): void {
            $default = $fallback->get($department->id, collect())->first();
            $department->default_user_id = $default?->id;
            $department->default_user_name = $default?->name;
            $department->effective_user_name = $department->manual_user_id !== null
                ? ($department->manual_user_active ? $department->manual_user_name : null) : $default?->name;
        });

        return Inertia::render('admin/DepartmentHeads', [
            'departments' => $departments,
            'users' => DB::table('users')->leftJoin('departments', 'departments.id', '=', 'users.department_id')
                ->where('users.is_active', true)->orderBy('users.name')
                ->get(['users.id', 'users.name', 'users.school_account', 'departments.name as department_name']),
            'events' => DB::table('department_head_assignment_events as events')
                ->join('departments', 'departments.id', '=', 'events.department_id')
                ->join('users as actors', 'actors.id', '=', 'events.actor_id')
                ->leftJoin('users as previous', 'previous.id', '=', 'events.old_user_id')
                ->leftJoin('users as current', 'current.id', '=', 'events.new_user_id')
                ->orderByDesc('events.id')->limit(30)
                ->get(['departments.name as department_name', 'actors.name as actor_name',
                    'previous.name as old_user_name', 'current.name as new_user_name', 'events.created_at']),
            'filters' => ['search' => $search],
        ]);
    }

    public function update(Request $request, int $department): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['system_admin']), 403);
        abort_unless(DB::table('departments')->where('id', $department)->exists(), 404);
        $data = $request->validate([
            'user_id' => ['present', 'nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
        ]);
        DB::transaction(function () use ($request, $department, $data): void {
            $current = DB::table('department_head_assignments')->where('department_id', $department)
                ->lockForUpdate()->first();
            $newId = $data['user_id'] === null ? null : (int) $data['user_id'];
            $oldId = $current?->user_id;
            if ($oldId === $newId) {
                return;
            }
            if ($newId === null) {
                DB::table('department_head_assignments')->where('department_id', $department)->delete();
            } else {
                abort_unless(User::query()->whereKey($newId)->where('is_active', true)->exists(), 422);
                DB::table('department_head_assignments')->updateOrInsert(
                    ['department_id' => $department],
                    ['user_id' => $newId, 'updated_by' => $request->user()->id,
                        'created_at' => $current?->created_at ?? now(), 'updated_at' => now()],
                );
            }
            DB::table('department_head_assignment_events')->insert([
                'department_id' => $department, 'actor_id' => $request->user()->id,
                'old_user_id' => $oldId, 'new_user_id' => $newId, 'created_at' => now(),
            ]);
        });
        $request->attributes->set('audit_subject', ['type' => 'department', 'id' => (string) $department]);

        return back()->with('status', $data['user_id'] === null ? '已清除手动指定，恢复按角色匹配。' : '部门负责人已保存。');
    }
}
