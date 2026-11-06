<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SchoolOrganizationSync
{
    /** @param array<string, mixed> $feed
     * @return array{departments: int, positions: int, users_created: int, users_updated: int, deactivated: int}
     */
    public function sync(array $feed, ?User $actor = null): array
    {
        $this->validateReferences($feed);

        return DB::transaction(function () use ($feed, $actor) {
            $now = now();
            foreach ($feed['departments'] as $item) {
                DB::table('departments')->insertOrIgnore([
                    'external_code' => $item['code'], 'name' => $item['name'],
                    'is_active' => $item['active'] ?? true, 'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('departments')->where('external_code', $item['code'])->update([
                    'name' => $item['name'], 'is_active' => $item['active'] ?? true, 'updated_at' => $now,
                ]);
            }
            $departments = DB::table('departments')->whereIn('external_code', array_column($feed['departments'], 'code'))
                ->pluck('id', 'external_code');
            foreach ($feed['departments'] as $item) {
                DB::table('departments')->where('id', $departments[$item['code']])
                    ->update(['parent_id' => isset($item['parent_code']) ? $departments[$item['parent_code']] : null]);
            }

            foreach ($feed['positions'] as $item) {
                DB::table('school_positions')->insertOrIgnore([
                    'code' => $item['code'], 'name' => $item['name'], 'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('school_positions')->where('code', $item['code'])->update([
                    'name' => $item['name'], 'updated_at' => $now,
                ]);
            }
            $positions = DB::table('school_positions')->whereIn('code', array_column($feed['positions'], 'code'))
                ->pluck('id', 'code');

            $created = 0;
            $updated = 0;
            foreach ($feed['people'] as $item) {
                $user = User::query()->where('school_account', $item['account'])->first();
                if ($user === null) {
                    $user = User::create([
                        'school_account' => $item['account'], 'name' => $item['name'],
                        'email' => $item['email'] ?? hash('sha256', $item['account']).'@school.invalid',
                        'password' => Str::random(64),
                        'email_verified_at' => $now,
                        'department_id' => $departments[$item['department_code']],
                        'school_position_id' => $positions[$item['position_code']],
                        'is_active' => $item['active'], 'school_synced_at' => $now,
                    ]);
                    $created++;
                } else {
                    $user->update([
                        'name' => $item['name'],
                        'email' => $item['email'] ?? $user->email,
                        'department_id' => $departments[$item['department_code']],
                        'school_position_id' => $positions[$item['position_code']],
                        'is_active' => $item['active'], 'school_synced_at' => $now,
                    ]);
                    $updated++;
                }
                $this->syncRoles($user, $positions[$item['position_code']]);
            }

            $deactivated = 0;
            if ($feed['complete_snapshot']) {
                $deactivated = User::query()->whereNotNull('school_synced_at')
                    ->whereNotIn('school_account', array_column($feed['people'], 'account'))
                    ->where('is_active', true)->update(['is_active' => false]);
            }
            DB::table('organization_sync_runs')->insert([
                'actor_id' => $actor?->id, 'complete_snapshot' => $feed['complete_snapshot'],
                'source' => $actor === null ? 'middata' : 'manual', 'status' => 'success',
                'departments_count' => count($feed['departments']),
                'positions_count' => count($feed['positions']),
                'people_count' => count($feed['people']),
                'deactivated_count' => $deactivated,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            return [
                'departments' => count($feed['departments']), 'positions' => count($feed['positions']),
                'users_created' => $created, 'users_updated' => $updated, 'deactivated' => $deactivated,
            ];
        });
    }

    public function syncRoles(User $user, int $positionId): void
    {
        DB::table('role_user')->where('user_id', $user->id)->where('source', 'school')->delete();
        $roleIds = DB::table('school_position_roles')->where('school_position_id', $positionId)->pluck('role_id');
        foreach ($roleIds as $roleId) {
            $alreadyAssigned = DB::table('role_user')->where('user_id', $user->id)->where('role_id', $roleId)->exists();
            if (! $alreadyAssigned) {
                DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => $roleId, 'source' => 'school']);
            }
        }
    }

    /** @param array<string, mixed> $feed */
    private function validateReferences(array $feed): void
    {
        $departments = [];
        foreach ($feed['departments'] as $item) {
            $departments[$item['code']] = $item;
        }
        $positions = [];
        foreach ($feed['positions'] as $item) {
            $positions[$item['code']] = true;
        }
        foreach ($feed['departments'] as $item) {
            $seen = [$item['code'] => true];
            $parent = $item['parent_code'] ?? null;
            while ($parent !== null) {
                if (! isset($departments[$parent]) || isset($seen[$parent])) {
                    throw ValidationException::withMessages(['departments' => '部门层级存在缺失或循环引用。']);
                }
                $seen[$parent] = true;
                $parent = $departments[$parent]['parent_code'] ?? null;
            }
        }
        foreach ($feed['people'] as $item) {
            if (! isset($departments[$item['department_code']]) || ! isset($positions[$item['position_code']])) {
                throw ValidationException::withMessages(['people' => '人员引用了不存在的部门或岗位。']);
            }
            if (isset($item['email'])) {
                $owner = User::query()->where('email', $item['email'])->first();
                if ($owner !== null && $owner->school_account !== $item['account']) {
                    throw ValidationException::withMessages(['people' => '邮箱已关联其他校内账号，请先核对人员映射。']);
                }
            }
        }
    }
}
