<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LegacyApprovalTimeline
{
    /** @param Collection<int, object> $records
     * @return list<array<string, string|null>>
     */
    public function nodes(object $project, Collection $records): array
    {
        $projectDetails = $this->details($project->details);
        $nodes = [];
        $submittedAt = $this->value($projectDetails['sbrq'] ?? null);
        if ($submittedAt !== null) {
            $nodes[] = [
                'key' => 'submit', 'label' => '提交', 'state' => 'done',
                'time' => $submittedAt, 'timeLabel' => '提交时间',
                'person' => $this->value($project->creator_name) ?? '原系统未记录',
                'personLabel' => '申请人', 'result' => '已提交', 'note' => null,
            ];
        }

        foreach ($records as $record) {
            if ($record->source_table !== 'zcy_progress_history') {
                continue;
            }
            $details = $this->details($record->details);
            $status = $this->value($record->status);
            $result = $status === null ? '原系统未记录' : (ctype_digit($status) ? '原系统状态码 '.$status : $status);
            $state = $status !== null && Str::contains($status, ['退回', '驳回', '不通过', '未通过'])
                ? 'stopped' : ($status !== null && Str::contains($status, ['通过', '完成', '已提交']) ? 'done' : 'recorded');
            $nodes[] = [
                'key' => 'history-'.$record->source_id,
                'label' => $this->value($details['lcsm'] ?? null) ?? $this->value($record->title) ?? '办理记录',
                'state' => $state,
                'time' => $this->value($record->occurred_at), 'timeLabel' => '处理时间',
                'person' => $this->value($details['zxr'] ?? null) ?? '原系统未记录',
                'personLabel' => '处理人', 'result' => $result, 'note' => null,
            ];
        }

        usort($nodes, fn (array $left, array $right) => strcmp($left['time'] ?? '9999', $right['time'] ?? '9999'));

        if ($nodes === []) {
            $nodes[] = [
                'key' => 'none', 'label' => '暂无办理记录', 'state' => 'waiting',
                'time' => null, 'timeLabel' => null, 'person' => null,
                'personLabel' => null, 'result' => null, 'note' => null,
            ];
        }

        return $nodes;
    }

    /** @return array<string, mixed> */
    private function details(mixed $value): array
    {
        $decoded = is_array($value) ? $value : json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function value(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
