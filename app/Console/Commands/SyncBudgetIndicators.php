<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SyncBudgetIndicators extends Command
{
    protected $signature = 'procurement:sync-budgets';

    protected $description = 'Synchronize budget codes from the read-only school middata source';

    public function handle(): int
    {
        try {
            $rows = [];
            $seen = [];
            $now = now();
            foreach (DB::connection('school_source')->table('t_ejxyybt_jfdmb')->orderBy('code')->cursor() as $row) {
                $code = trim((string) $row->code);
                if ($code === '') {
                    continue;
                }
                if (isset($seen[$code])) {
                    throw new RuntimeException("Duplicate budget code in school source: {$code}");
                }
                $seen[$code] = true;
                $name = trim((string) $row->name);
                $rows[] = [
                    'source_id' => 'school_source:'.$code,
                    'source' => 'school_source',
                    'code' => $code,
                    'name' => $name !== '' ? $name : '未命名经费项目',
                    'director' => $this->nullableText($row->charge_name),
                    'department_code' => $this->nullableText($row->departcode),
                    'department_name' => $this->nullableText($row->departname),
                    'status' => strtoupper(trim((string) $row->validity)) === 'Y' ? '1' : '0',
                    'available_cents' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($rows === []) {
                throw new RuntimeException('School budget source returned no usable codes');
            }

            DB::transaction(function () use ($rows, $now): void {
                DB::table('budget_indicators')->where('source', 'school_source')
                    ->update(['status' => '0', 'updated_at' => $now]);
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('budget_indicators')->upsert($chunk, ['source_id'], [
                        'source', 'code', 'name', 'director', 'department_code',
                        'department_name', 'status', 'available_cents', 'updated_at',
                    ]);
                }
            });
            $this->info('已同步 '.count($rows).' 条经费代码，其中启用 '.count(array_filter($rows, fn (array $row) => $row['status'] === '1')).' 条。');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('经费代码同步失败，本地数据未更改。请检查 162 源库连接、表结构及应用日志。');

            return self::FAILURE;
        }
    }

    private function nullableText(?string $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
