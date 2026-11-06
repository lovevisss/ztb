<?php

namespace App\Console\Commands;

use App\Services\LegacyProcurementImport;
use Illuminate\Console\Command;
use Throwable;

class ImportLegacyProcurement extends Command
{
    protected $signature = 'legacy:import-procurement';

    protected $description = 'Import read-only procurement history from the old system';

    public function handle(LegacyProcurementImport $import): int
    {
        try {
            foreach ($import->run() as $table => $count) {
                $this->line("{$table}: {$count}");
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('旧采购历史导入失败，详情见应用日志。');

            return self::FAILURE;
        }
    }
}
