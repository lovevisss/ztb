<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ManualRoleAssignments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SchoolIdentityController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(['system_admin']), 403);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $users = DB::table('users')
            ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
            ->when($filters['search'] ?? null, fn ($query, $value) => $query->where(function ($scope) use ($value) {
                $scope->where('users.name', 'like', '%'.$value.'%')
                    ->orWhere('users.email', 'like', '%'.$value.'%')
                    ->orWhere('users.school_account', 'like', '%'.$value.'%');
            }))
            ->orderBy('users.name')
            ->paginate(30, ['users.id', 'users.name', 'users.email', 'users.school_account', 'users.is_active', 'departments.name as department_name'])
            ->withQueryString();
        $userIds = $users->getCollection()->pluck('id');
        $assignments = DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->whereIn('role_user.user_id', $userIds)
            ->get(['role_user.user_id', 'role_user.source', 'roles.code'])->groupBy('user_id');
        $users->getCollection()->each(function ($user) use ($assignments): void {
            $roles = $assignments->get($user->id, collect());
            $user->manual_role_codes = $roles->where('source', 'manual')->pluck('code')->values()->all();
            $user->school_role_codes = $roles->where('source', 'school')->pluck('code')->values()->all();
        });
        $events = DB::table('manual_role_events')->leftJoin('users as actors', 'actors.id', '=', 'manual_role_events.actor_id')
            ->whereIn('manual_role_events.user_id', $userIds)
            ->orderByDesc('manual_role_events.id')->limit(100)
            ->get(['manual_role_events.user_id', 'manual_role_events.role_code', 'manual_role_events.action',
                'manual_role_events.channel', 'manual_role_events.created_at', 'actors.name as actor_name']);

        return Inertia::render('admin/SchoolIdentities', [
            'users' => $users, 'filters' => $filters,
            'roles' => DB::table('roles')->orderBy('id')->get(['code', 'name']),
            'roleEvents' => $events,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['system_admin']), 403);
        $data = $request->validate([
            'school_account' => ['required', 'string', 'max:128', Rule::unique('users', 'school_account')->ignore($user->id)],
            'is_active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $user, $data) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $oldAccount = $locked->school_account;
            $oldActive = $locked->is_active;
            $locked->update($data);
            DB::table('school_identity_events')->insert([
                'user_id' => $locked->id, 'actor_id' => $request->user()->id,
                'old_school_account' => $oldAccount, 'new_school_account' => $data['school_account'],
                'old_active' => $oldActive, 'new_active' => $data['is_active'],
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return to_route('school-identities.index');
    }

    public function updateRoles(Request $request, User $user, ManualRoleAssignments $assignments): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['system_admin']), 403);
        $data = $request->validate([
            'manual_role_codes' => ['present', 'array'],
            'manual_role_codes.*' => ['string', 'distinct', Rule::exists('roles', 'code')],
        ]);
        $assignments->replace($user, $data['manual_role_codes'], $request->user());

        return back()->with('status', '手动角色已更新。');
    }
}
