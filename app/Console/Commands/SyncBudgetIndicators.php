<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncBudgetIndicators extends Command
{
    protected $signature = 'procurement:sync-budgets';

    protected $description = 'Synchronize budget indicators from the read-only legacy source';

    public function handle(): int
    {
        try {
            $count = 0;
            foreach (DB::connection('legacy_purchase')->table('zcy_jfly_n')->orderBy('id')->cursor() as $row) {
                DB::table('budget_indicators')->updateOrInsert(['source_id' => (string) $row->id], [
                    'code' => $row->ysbm ?: null,
                    'name' => $row->xmmc ?: '未命名预算项目',
                    'director' => $row->xmfzr ?: null,
                    'department_code' => $row->jbbmbm ?: null,
                    'status' => $row->status === null || $row->status === '' ? null : (string) $row->status,
                    'available_cents' => is_numeric($row->syje) ? max(0, (int) round((float) $row->syje * 100)) : null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
                $count++;
            }
            $this->info("已同步 {$count} 条预算指标。");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('预算指标同步失败，请检查旧库连接及日志。');

            return self::FAILURE;
        }
    }
}
