<?php

namespace App\Console\Commands;

use App\Services\MiddataOrganizationFeed;
use App\Services\SchoolOrganizationSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncSchoolOrganization extends Command
{
    protected $signature = 'school:sync-organization';

    protected $description = 'Synchronize departments and active staff from middata';

    public function handle(MiddataOrganizationFeed $source, SchoolOrganizationSync $sync): int
    {
        try {
            $result = $sync->sync($source->read());
            $this->info(sprintf(
                '院系 %d，岗位 %d，新建人员 %d，更新人员 %d，停用人员 %d。',
                $result['departments'], $result['positions'], $result['users_created'],
                $result['users_updated'], $result['deactivated'],
            ));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            try {
                DB::table('organization_sync_runs')->insert([
                    'actor_id' => null, 'source' => 'middata', 'status' => 'failed',
                    'error' => mb_substr($exception->getMessage(), 0, 2000),
                    'complete_snapshot' => true, 'departments_count' => 0,
                    'positions_count' => 0, 'people_count' => 0, 'deactivated_count' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            } catch (Throwable $loggingException) {
                report($loggingException);
            }
            $this->error('学校组织同步失败，详情见应用日志及同步记录。');

            return self::FAILURE;
        }
    }
}
