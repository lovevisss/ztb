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
                ];
            });

        return $current->concat($legacy)->sortByDesc('updated_at')->values();
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
            foreach (['number', 'title', 'department_name', 'applicant_name', 'status'] as $field) {
                $needle = trim((string) ($filters[$field] ?? ''));
                if ($needle !== '' && mb_stripos((string) ($item[$field] ?? ''), $needle) === false) {
                    return false;
                }
            }

            return true;
        })->values();
    }
}
