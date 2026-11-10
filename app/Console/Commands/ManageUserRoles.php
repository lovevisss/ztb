<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ManualRoleAssignments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageUserRoles extends Command
{
    protected $signature = 'school:manage-user-roles
        {account : Exact school account}
        {--grant=* : Role code to grant (repeatable)}
        {--revoke=* : Role code to revoke (repeatable)}
        {--allow-last-admin : Explicitly permit revoking the last active system administrator}
        {--allow-last-manager : Explicitly permit revoking the last active procurement manager}';

    protected $description = 'Grant or revoke manual roles for one existing school account';

    public function handle(ManualRoleAssignments $assignments): int
    {
        $account = trim((string) $this->argument('account'));
        $grants = array_values(array_unique($this->option('grant')));
        $revocations = array_values(array_unique($this->option('revoke')));
        if ($account === '' || ($grants === [] && $revocations === []) || array_intersect($grants, $revocations)) {
            $this->error('请提供准确工号，并指定互不冲突的 --grant 或 --revoke 角色代码。');

            return self::FAILURE;
        }
        $requested = array_values(array_unique(array_merge($grants, $revocations)));
        if (DB::table('roles')->whereIn('code', $requested)->count() !== count($requested)) {
            $this->error('角色代码无效；未修改任何权限。');

            return self::FAILURE;
        }
        $user = User::query()->where('school_account', $account)->first();
        if ($user === null) {
            $this->error('未找到该校内账号；不会创建账号。');

            return self::FAILURE;
        }
        $manual = DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)->where('role_user.source', 'manual')
            ->pluck('roles.code')->all();
        $desired = array_values(array_unique(array_diff(array_merge($manual, $grants), $revocations)));
        try {
            $operator = getenv('USERNAME') ?: getenv('USER') ?: 'unknown';
            $result = $assignments->replace(
                $user, $desired, null, 'console:'.substr($operator, 0, 48),
                (bool) $this->option('allow-last-admin'), (bool) $this->option('allow-last-manager'),
            );
        } catch (ValidationException $exception) {
            $this->error(implode('；', array_merge(...array_values($exception->errors()))));

            return self::FAILURE;
        }
        $this->info(sprintf(
            '工号 %s：新增 %s；撤销 %s。',
            $account,
            $result['granted'] ? implode('、', $result['granted']) : '无',
            $result['revoked'] ? implode('、', $result['revoked']) : '无',
        ));

        return self::SUCCESS;
    }
}
