<?php

namespace App\Services;

use App\Models\ProcurementApplication;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MyProcurementApplications
{
    private const STATUS_LABELS = [
        'draft' => '草稿', 'returned' => '退回修改', 'submitted' => '审批中',
        'rejected' => '已驳回', 'approved' => '审批通过', 'executing' => '执行中',
        'execution_completed' => '执行已完成', 'archived' => '已归档',
    ];

    /** @return Collection<int, array<string, mixed>> */
    public function records(User $user): Collection
    {
        $departments = DB::table('departments')->pluck('name', 'id');
        $current = ProcurementApplication::query()
            ->where('applicant_id', $user->id)
            ->get()
            ->map(fn (ProcurementApplication $item) => [
                'key' => 'current-'.$item->id,
                'id' => $item->id,
                'source_id' => null,
                'source' => '新系统',
                'application_form' => $item->application_form,
                'category' => $item->category,
                'number' => $item->project_number,
                'title' => $item->title,
                'department_name' => $departments[$item->department_id] ?? null,
                'amount_cents' => $item->budget_cents,
                'submitted_at' => $item->submitted_at?->toDateTimeString(),
                'applicant_name' => $user->name,
                'contact_phone' => $item->contact_phone,
                'status' => self::STATUS_LABELS[$item->status] ?? $item->status,
                'tab' => in_array($item->status, ['draft', 'returned'], true) ? 'draft' : 'submitted',
                'updated_at' => $item->updated_at?->toDateTimeString(),
                'created_at' => $item->created_at?->toDateTimeString(),
            ]);

        $legacy = $user->school_account === null ? collect() : DB::table('legacy_procurement_records')
            ->where('source_table', 'zcy_cgsq')
            ->where('creator_account', $user->school_account)
            ->get(['source_id', 'number', 'title', 'amount_cents', 'status', 'occurred_at', 'creator_name', 'department_id', 'details'])
            ->map(function ($item) use ($departments) {
                $details = json_decode((string) $item->details, true);
                $details = is_array($details) ? $details : [];

                return [
                    'key' => 'legacy-'.$item->source_id,
                    'id' => null,
                    'source_id' => $item->source_id,
                    'source' => '旧系统',
                    'application_form' => null,
                    'category' => null,
                    'number' => $item->number,
                    'title' => $item->title,
                    'department_name' => $details['sbbm'] ?? ($departments[$item->department_id] ?? null),
                    'amount_cents' => $item->amount_cents,
                    'submitted_at' => isset($details['sbrq']) ? (string) $details['sbrq'] : $item->occurred_at,
                    'applicant_name' => $item->creator_name,
                    'contact_phone' => $details['jbrdh'] ?? null,
                    'status' => $item->status ?: '未知',
                    'tab' => $item->status === '未提交' ? 'draft' : 'submitted',
                    'updated_at' => $item->occurred_at,
                    'created_at' => $item->occurred_at,
                ];
            });

        return $this->markRegularProjects($current->concat($legacy)->sortByDesc('updated_at')->values());
    }

    /** @param Collection<int, array<string, mixed>> $records
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function filter(Collection $records, array $filters): Collection
    {
        return $records->filter(function (array $item) use ($filters) {
            if ($item['tab'] !== ($filters['tab'] ?? 'submitted')) {
                return false;
            }
            if (isset($filters['year']) && $item['year'] !== (int) $filters['year']) {
                return false;
            }
            if (($filters['regular'] ?? 'all') === 'regular' && ! $item['is_regular']) {
                return false;
            }
            if (($filters['regular'] ?? 'all') === 'other' && $item['is_regular']) {
                return false;
            }
            foreach (['number', 'title', 'department_name', 'applicant_name', 'status'] as $field) {
                $needle = trim((string) ($filters[$field] ?? ''));
                if ($needle !== '' && mb_stripos((string) ($item[$field] ?? ''), $needle) === false) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /** @param Collection<int, array<string, mixed>> $records
     * @return Collection<int, array<string, mixed>>
     */
    private function markRegularProjects(Collection $records): Collection
    {
        $records = $records->map(function (array $record) {
            $date = $record['source'] === '旧系统' && $record['tab'] === 'draft'
                ? $record['created_at'] : ($record['submitted_at'] ?: $record['created_at']);
            $record['year'] = preg_match('/^(\d{4})-\d{2}-\d{2}/', (string) $date, $matches)
                ? (int) $matches[1] : null;
            $record['is_regular'] = false;
            $record['matching_years'] = [];

            return $record;
        });

        $eligible = $records->filter(fn (array $record) => $record['year'] !== null
            && $record['amount_cents'] !== null
            && ($record['source'] === '新系统'
                ? $record['category'] !== '工程类' && in_array($record['status'], ['审批通过', '执行中', '执行已完成', '已归档'], true)
                : $record['status'] === '审批通过'));
        $newIds = $eligible->where('source', '新系统')->pluck('id')->all();
        $legacyIds = $eligible->where('source', '旧系统')->pluck('source_id')->all();
        $items = [];

        foreach (array_chunk($newIds, 500) as $ids) {
            foreach (DB::table('procurement_line_items')->whereIn('application_id', $ids)
                ->get(['application_id', 'name', 'specification', 'quantity', 'unit', 'unit_price_cents', 'total_cents']) as $line) {
                $items['current-'.$line->application_id][] = $this->itemSignature(
                    $line->name, $line->specification, $line->quantity, $line->unit,
                    $line->unit_price_cents, $line->total_cents,
                );
            }
        }
        foreach (array_chunk($legacyIds, 500) as $ids) {
            foreach (DB::table('legacy_procurement_records')->where('source_table', 'zcy_cgsq_qd')
                ->whereIn('application_source_id', $ids)->get(['application_source_id', 'details']) as $line) {
                $details = json_decode((string) $line->details, true);
                if (! is_array($details)) {
                    $items['legacy-'.$line->application_source_id][] = null;

                    continue;
                }
                $items['legacy-'.$line->application_source_id][] = $this->itemSignature(
                    $details['mc'] ?? null, $details['ggxh'] ?? null, $details['sl'] ?? null,
                    $details['dw'] ?? null, $this->yuanToCents($details['dj'] ?? null),
                    $this->yuanToCents($details['je'] ?? null),
                );
            }
        }

        $groups = [];
        foreach ($eligible as $record) {
            $lines = $items[$record['key']] ?? [];
            if ($lines === [] || in_array(null, $lines, true)) {
                continue;
            }
            sort($lines, SORT_STRING);
            $signature = json_encode([(int) $record['amount_cents'], $lines], JSON_UNESCAPED_UNICODE);
            $groups[$signature][] = $record;
        }

        $matches = [];
        foreach ($groups as $group) {
            $years = array_values(array_unique(array_column($group, 'year')));
            if (count($years) < 2) {
                continue;
            }
            sort($years, SORT_NUMERIC);
            foreach ($group as $record) {
                $matches[$record['key']] = array_values(array_filter($years, fn (int $year) => $year !== $record['year']));
            }
        }

        return $records->map(function (array $record) use ($matches) {
            if (isset($matches[$record['key']])) {
                $record['is_regular'] = true;
                $record['matching_years'] = $matches[$record['key']];
            }

            unset($record['category'], $record['created_at']);

            return $record;
        });
    }

    private function itemSignature(mixed $name, mixed $specification, mixed $quantity, mixed $unit, mixed $unitPriceCents, mixed $totalCents): ?string
    {
        if ($this->normalizedText($name) === '' || ! is_numeric($quantity) || (float) $quantity <= 0
            || ! is_numeric($unitPriceCents) || ! is_numeric($totalCents)) {
            return null;
        }

        return json_encode([
            $this->normalizedText($name), $this->normalizedText($specification),
            number_format((float) $quantity, 3, '.', ''), $this->normalizedText($unit),
            (int) $unitPriceCents, (int) $totalCents,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function normalizedText(mixed $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) ($value ?? '')) ?? '');
    }

    private function yuanToCents(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value * 100) : null;
    }
}
