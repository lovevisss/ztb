<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualRoleAssignments
{
    /**
     * Replace only this user's manually assigned roles. School-derived roles are untouched.
     *
     * @param list<string> $codes
     * @return array{granted: list<string>, revoked: list<string>}
     */
    public function replace(
        User $user,
        array $codes,
        ?User $actor = null,
        string $channel = 'web',
        bool $allowLastAdmin = false,
        bool $allowLastManager = false,
    ): array {
        return DB::transaction(function () use ($user, $codes, $actor, $channel, $allowLastAdmin, $allowLastManager): array {
            $locked = DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            if ($locked === null) {
                throw ValidationException::withMessages(['manual_role_codes' => '人员不存在。']);
            }

            $desired = array_values(array_unique($codes));
            $roles = DB::table('roles')->whereIn('code', $desired)->pluck('id', 'code');
            if ($roles->count() !== count($desired)) {
                throw ValidationException::withMessages(['manual_role_codes' => '角色代码无效。']);
            }
            $assigned = DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->where('role_user.user_id', $user->id)
                ->get(['roles.code', 'role_user.source']);
            $manual = $assigned->where('source', 'manual')->pluck('code')->all();
            $school = $assigned->where('source', 'school')->pluck('code')->all();
            if (array_intersect($desired, $school)) {
                throw ValidationException::withMessages(['manual_role_codes' => '岗位继承的角色不可作为手动角色编辑。']);
            }

            $granted = array_values(array_diff($desired, $manual));
            $revoked = array_values(array_diff($manual, $desired));
            if ($granted !== [] && ! $locked->is_active) {
                throw ValidationException::withMessages(['manual_role_codes' => '停用账号不能新增角色。']);
            }
            foreach (['system_admin' => $allowLastAdmin, 'procurement_manager' => $allowLastManager] as $code => $allowed) {
                if (! in_array($code, $revoked, true) || $allowed) {
                    continue;
                }
                DB::table('roles')->where('code', $code)->lockForUpdate()->first();
                $remaining = DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
                    ->join('users', 'users.id', '=', 'role_user.user_id')
                    ->where('roles.code', $code)->where('users.is_active', true)
                    ->where('users.id', '!=', $user->id)->exists();
                if (! $remaining) {
                    throw ValidationException::withMessages([
                        'manual_role_codes' => $code === 'system_admin'
                            ? '不能撤销最后一名有效系统管理员。'
                            : '不能撤销最后一名有效采购管理人员。',
                    ]);
                }
            }

            foreach ($revoked as $code) {
                DB::table('role_user')->where('user_id', $user->id)->where('role_id', DB::table('roles')->where('code', $code)->value('id'))
                    ->where('source', 'manual')->delete();
                $this->record($user, $actor, $code, 'revoked', $channel);
            }
            foreach ($granted as $code) {
                DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => $roles[$code], 'source' => 'manual']);
                $this->record($user, $actor, $code, 'granted', $channel);
            }

            return ['granted' => $granted, 'revoked' => $revoked];
        });
    }

    private function record(User $user, ?User $actor, string $code, string $action, string $channel): void
    {
        DB::table('manual_role_events')->insert([
            'user_id' => $user->id, 'actor_id' => $actor?->id,
            'role_code' => $code, 'action' => $action, 'channel' => $channel,
            'created_at' => now(),
        ]);
    }
}
