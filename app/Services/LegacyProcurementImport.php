<?php

namespace App\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LegacyProcurementImport
{
    /** @return array<string, int> */
    public function run(): array
    {
        $source = DB::connection('legacy_purchase');
        $applications = $source->table('zcy_cgsq')->get();
        if ($applications->isEmpty()) {
            throw new RuntimeException('旧采购申请表为空，导入已取消。');
        }

        $users = DB::table('users')->whereNotNull('school_account')->pluck('id', 'school_account');
        $departments = DB::table('departments')->whereNotNull('external_code')->pluck('id', 'external_code');
        $appMeta = [];
        foreach ($applications as $row) {
            $id = (string) $row->id;
            $appMeta[$id] = [
                'application_source_id' => $id,
                'creator_account' => $row->create_by,
                'creator_name' => $row->create_name,
                'department_code' => $row->sbbmbh,
                'user_id' => $users[$row->create_by] ?? null,
                'department_id' => $departments[$row->sbbmbh] ?? null,
            ];
        }

        $itemApps = $source->table('zcy_cgsq_qd')->pluck('cgsqid', 'id')->map(fn ($id) => (string) $id)->all();
        $taskApps = $this->parentApps($source->table('zcy_cgrw_qd')->get(['cgrwid', 'sbid']), 'cgrwid', $itemApps);
        $packageApps = $this->parentApps($source->table('zcy_cgb_qd')->get(['cgbid', 'sbid']), 'cgbid', $itemApps);
        $resultApps = [];
        foreach ($source->table('zcy_zbjg')->get(['id', 'cgrwid']) as $result) {
            $resultApps[(string) $result->id] = $taskApps[(string) $result->cgrwid] ?? null;
        }

        $specs = [
            'zcy_cgsq' => [null, 'cgbh', 'xmmc', 'bpm_status', 'ysje', 'create_date'],
            'zcy_cgsq_qd' => [$itemApps, 'hwbh', 'mc', 'bpm_status', 'je', 'create_date'],
            'zcy_cgrw' => [$taskApps, 'cgrwbh', 'cgrwmc', 'bpm_status', 'ysje', 'create_date'],
            'zcy_cgrw_qd' => [null, null, null, null, null, 'create_date'],
            'zcy_cgb' => [$packageApps, 'cgbbh', 'cgbmc', 'bpm_status', 'ysje', 'create_date'],
            'zcy_cgb_qd' => [null, null, null, null, null, 'create_date'],
            'zcy_zbjg' => [$resultApps, 'jgbh', 'xmmc', 'cg_status', 'cjjg', 'create_date'],
            'zcy_ht' => [null, 'htbh', 'htmc', 'spzt', 'htje', 'create_date'],
            'zcy_progress_history' => [null, null, 'lcsm', 'bpm_status', null, 'create_date'],
        ];
        $counts = [];
        foreach ($specs as $table => [$map, $number, $title, $status, $amount, $date]) {
            $batch = [];
            $counts[$table] = 0;
            foreach ($source->table($table)->orderBy('id')->cursor() as $row) {
                $id = (string) $row->id;
                $appId = match ($table) {
                    'zcy_cgsq' => $id,
                    'zcy_cgrw_qd' => $taskApps[(string) $row->cgrwid] ?? null,
                    'zcy_cgb_qd' => $packageApps[(string) $row->cgbid] ?? null,
                    'zcy_ht' => $resultApps[(string) $row->jgid] ?? null,
                    'zcy_progress_history' => $this->historyApplication($row, $appMeta, $packageApps),
                    default => $map[$id] ?? null,
                };
                if ($appId !== null && ! isset($appMeta[$appId])) {
                    $appId = null;
                }
                $meta = $appMeta[$appId] ?? [];
                $batch[] = [
                    'source_table' => $table, 'source_id' => $id,
                    'application_source_id' => $appId,
                    'user_id' => $meta['user_id'] ?? null,
                    'department_id' => $meta['department_id'] ?? null,
                    'creator_account' => $meta['creator_account'] ?? ($row->create_by ?? null),
                    'creator_name' => $meta['creator_name'] ?? ($row->create_name ?? null),
                    'department_code' => $meta['department_code'] ?? null,
                    'number' => $number ? ($row->{$number} ?? null) : null,
                    'title' => $title ? mb_substr((string) ($row->{$title} ?? ''), 0, 255) : null,
                    'status' => $status ? mb_substr((string) ($row->{$status} ?? ''), 0, 255) : null,
                    'amount_cents' => $amount ? $this->cents($row->{$amount} ?? null) : null,
                    'occurred_at' => $row->{$date} ?? null,
                    'details' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ];
                if (count($batch) === 200) {
                    $this->saveRecords($batch);
                    $batch = [];
                }
                $counts[$table] = ($counts[$table] ?? 0) + 1;
            }
            $this->saveRecords($batch);
        }

        $counts['attachments'] = $this->importAttachments($source, $appMeta, $itemApps, $resultApps);

        return $counts;
    }

    /** @param iterable<object> $links
     * @param  array<string, string>  $itemApps
     * @return array<string, string>
     */
    private function parentApps(iterable $links, string $parentField, array $itemApps): array
    {
        $sets = [];
        foreach ($links as $link) {
            $appId = $itemApps[(string) data_get($link, 'sbid')] ?? null;
            if ($appId !== null) {
                $sets[(string) data_get($link, $parentField)][$appId] = true;
            }
        }
        $result = [];
        foreach ($sets as $parentId => $apps) {
            if (count($apps) === 1) {
                $result[$parentId] = (string) array_key_first($apps);
            }
        }

        return $result;
    }

    /** @param array<string, array<string, mixed>> $apps
     * @param  array<string, string>  $packages
     */
    private function historyApplication(object $row, array $apps, array $packages): ?string
    {
        $id = (string) data_get($row, 'primarykey');

        return match (data_get($row, 'tablename')) {
            'zcy_cgsq' => isset($apps[$id]) ? $id : null,
            'zcy_cgb' => $packages[$id] ?? null,
            default => null,
        };
    }

    private function cents(mixed $amount): ?int
    {
        return is_numeric($amount) && (float) $amount >= 0 ? (int) round((float) $amount * 100) : null;
    }

    /** @param list<array<string, mixed>> $batch */
    private function saveRecords(array $batch): void
    {
        if ($batch !== []) {
            DB::table('legacy_procurement_records')->upsert($batch, ['source_table', 'source_id'], [
                'application_source_id', 'user_id', 'department_id', 'creator_account', 'creator_name',
                'department_code', 'number', 'title', 'status', 'amount_cents', 'occurred_at', 'details', 'updated_at',
            ]);
        }
    }

    /** @param array<string, array<string, mixed>> $apps
     * @param  array<string, string>  $items
     * @param  array<string, string|null>  $results
     */
    private function importAttachments(ConnectionInterface $source, array $apps, array $items, array $results): int
    {
        $owners = [
            'zcy_cgsq' => array_fill_keys(array_keys($apps), true),
            'zcy_cgsq_qd' => array_fill_keys(array_keys($items), true),
            'zcy_zbjg' => array_fill_keys(array_keys($results), true),
            'zcy_ht' => DB::table('legacy_procurement_records')->where('source_table', 'zcy_ht')->pluck('source_id')->mapWithKeys(fn ($id) => [(string) $id => true])->all(),
        ];
        $count = 0;
        foreach ($source->table('cgform_uploadfiles as f')->join('t_s_attachment as a', 'a.ID', '=', 'f.id')
            ->whereIn('f.CGFORM_NAME', array_keys($owners))
            ->get(['f.id', 'f.CGFORM_NAME', 'f.CGFORM_ID', 'a.attachmenttitle', 'a.realpath', 'a.extend']) as $file) {
            $table = (string) $file->CGFORM_NAME;
            $ownerId = (string) $file->CGFORM_ID;
            if (! isset($owners[$table][$ownerId])) {
                continue;
            }
            $appId = match ($table) {
                'zcy_cgsq' => $ownerId,
                'zcy_cgsq_qd' => $items[$ownerId] ?? null,
                'zcy_zbjg' => $results[$ownerId] ?? null,
                default => DB::table('legacy_procurement_records')->where('source_table', 'zcy_ht')->where('source_id', $ownerId)->value('application_source_id'),
            };
            DB::table('legacy_procurement_attachments')->upsert([[
                'source_table' => 't_s_attachment', 'source_id' => (string) $file->id,
                'owner_table' => $table, 'owner_source_id' => $ownerId,
                'application_source_id' => $appId,
                'name' => $file->attachmenttitle, 'legacy_path' => $file->realpath,
                'mime_type' => $file->extend, 'migration_status' => 'pending_file',
                'created_at' => now(), 'updated_at' => now(),
            ]], ['source_table', 'source_id', 'owner_table', 'owner_source_id'],
                ['application_source_id', 'name', 'legacy_path', 'mime_type', 'updated_at']);
            $count++;
        }

        return $count;
    }
}
