<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SyncSchoolPhones extends Command
{
    protected $signature = 'school:sync-phones {--dry-run : Preview changes without writing to users}';

    protected $description = 'Update existing users mobile phones from middata yddh by school account';

    public function handle(): int
    {
        try {
            $people = DB::connection('school_source')->table('t_cx_zzqxryxx')
                ->whereIn('rylx', ['1', '2'])->get(['xgh', 'yddh']);
            if ($people->isEmpty()) {
                throw new RuntimeException('来源人员表为空，已取消手机号同步。');
            }

            $phones = [];
            $skipped = 0;
            foreach ($people as $person) {
                $account = trim((string) $person->xgh);
                $phone = trim((string) $person->yddh);
                if ($account === '' || $phone === '' || mb_strlen($phone) > 30) {
                    $skipped++;
                    continue;
                }
                if (isset($phones[$account])) {
                    throw new RuntimeException('来源人员工号重复，已取消手机号同步。');
                }
                $phones[$account] = $phone;
            }
            if ($phones === []) {
                throw new RuntimeException('来源人员没有有效手机号，已取消同步。');
            }

            $result = DB::transaction(function () use ($phones, $skipped) {
                $counts = ['source' => count($phones), 'matched' => 0, 'changed' => 0, 'unchanged' => 0, 'unmatched' => 0, 'skipped' => $skipped];
                foreach (array_chunk($phones, 500, true) as $batch) {
                    $users = DB::table('users')->whereIn('school_account', array_keys($batch))
                        ->get(['id', 'school_account', 'mobile_phone'])->keyBy('school_account');
                    foreach ($batch as $account => $phone) {
                        $user = $users->get($account);
                        if ($user === null) {
                            $counts['unmatched']++;
                            continue;
                        }
                        $counts['matched']++;
                        if ($user->mobile_phone === $phone) {
                            $counts['unchanged']++;
                            continue;
                        }
                        $counts['changed']++;
                        if (! $this->option('dry-run')) {
                            DB::table('users')->where('id', $user->id)->update(['mobile_phone' => $phone]);
                        }
                    }
                }

                return $counts;
            });

            $this->info(($this->option('dry-run') ? '预览：' : '完成：').sprintf(
                '来源有效号码 %d，匹配账号 %d，%s %d，未变化 %d，未匹配 %d，跳过空值或无效值 %d。',
                $result['source'], $result['matched'], $this->option('dry-run') ? '待更新' : '已更新',
                $result['changed'], $result['unchanged'], $result['unmatched'], $result['skipped'],
            ));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('手机号同步失败，详情见应用日志。');

            return self::FAILURE;
        }
    }
}
