<?php

namespace App\Services;

use App\Models\ProcurementApplication;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProcurementStatistics
{
    public function __construct(private readonly ProcurementAccess $access) {}

    /** @param array<string, mixed> $filters
     * @return Collection<int, ProcurementStatisticRecord>
     */
    public function records(User $user, array $filters): Collection
    {
        $rows = ProcurementApplication::query()
            ->whereIn('procurement_applications.id', $this->access->visibleHistoryQuery($user)->select('procurement_applications.id'))
            ->leftJoin('departments', 'departments.id', '=', 'procurement_applications.department_id')
            ->leftJoin('procurement_executions', 'procurement_executions.application_id', '=', 'procurement_applications.id')
            ->when($filters['year'] ?? null, fn ($query, $year) => $query->whereYear('procurement_applications.submitted_at', $year))
            ->when($filters['department_id'] ?? null, fn ($query, $id) => $query->where('procurement_applications.department_id', $id))
            ->when($filters['responsible_department'] ?? null, fn ($query, $value) => $query->where('procurement_applications.responsible_department', $value))
            ->when($filters['purchase_method'] ?? null, fn ($query, $value) => $query->where('procurement_applications.purchase_method', $value))
            ->when($filters['organization_form'] ?? null, fn ($query, $value) => $query->where('procurement_applications.organization_form', $value))
            ->when($filters['funding_source'] ?? null, fn ($query, $value) => $query->where('procurement_applications.funding_source', $value))
            ->when($filters['purchase_type'] ?? null, fn ($query, $value) => $query->where('procurement_applications.purchase_type', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('procurement_applications.status', $value))
            ->when($filters['execution_status'] ?? null, fn ($query, $value) => $value === 'not_started'
                ? $query->whereNull('procurement_executions.status')
                : $query->where('procurement_executions.status', $value))
            ->when($filters['archive_status'] ?? null, fn ($query, $value) => $query->where('procurement_applications.status', $value === 'archived' ? '=' : '!=', 'archived'))
            ->orderByDesc('procurement_applications.submitted_at')
            ->get([
                'procurement_applications.id', 'procurement_applications.project_number',
                'procurement_applications.title', 'procurement_applications.department_id',
                'departments.name as department_name', 'procurement_applications.responsible_department',
                'procurement_applications.purchase_method', 'procurement_applications.organization_form',
                'procurement_applications.funding_source', 'procurement_applications.purchase_type',
                'procurement_applications.status', 'procurement_applications.budget_cents',
                'procurement_applications.submitted_at', 'procurement_executions.actual_amount_cents',
                'procurement_executions.status as execution_status',
            ]);

        return collect($rows->all())->map(fn (ProcurementApplication $row) => ProcurementStatisticRecord::fromModel($row));
    }

    /** @param Collection<int, ProcurementStatisticRecord> $records
     * @return array<string, mixed>
     */
    public function dashboard(Collection $records): array
    {
        $approvalDates = DB::table('approval_events')
            ->whereIn('application_id', $records->pluck('id')->all())
            ->where('event', 'approved')
            ->select('application_id', DB::raw('MAX(created_at) as approved_at'))
            ->groupBy('application_id')->pluck('approved_at', 'application_id');
        $durations = $records->filter(fn ($row) => in_array($row->status, ['approved', 'executing', 'execution_completed', 'archived'], true)
            && $row->submitted_at !== null && $approvalDates->has($row->id))
            ->map(fn ($row) => max(0, (strtotime($approvalDates[$row->id]) - strtotime($row->submitted_at)) / 3600));

        return [
            'summary' => [
                'projectCount' => $records->count(),
                'budgetCents' => $records->sum('budget_cents'),
                'actualCents' => $records->sum('actual_amount_cents'),
                'selfPurchaseCount' => $records->where('purchase_type', 'self_purchase')->count(),
                'centralTenderCount' => $records->where('purchase_type', 'central_tender')->count(),
                'pendingArchiveCount' => $records->where('status', 'execution_completed')->count(),
                'averageApprovalHours' => $durations->isEmpty() ? null : round($durations->avg(), 1),
            ],
            'byDepartment' => $this->group($records, 'department_name'),
            'departmentRanking' => $this->group($records, 'department_name'),
            'byResponsibleDepartment' => $this->group($records, 'responsible_department'),
            'byPurchaseMethod' => $this->group($records, 'purchase_method'),
            'byOrganizationForm' => $this->group($records, 'organization_form'),
            'byFundingSource' => $this->group($records, 'funding_source'),
            'byPurchaseType' => $this->group($records, 'purchase_type'),
            'byStatus' => $this->group($records, 'status'),
            'byExecutionStatus' => $this->group($records, 'execution_state'),
            'byArchiveStatus' => $this->group($records, 'archive_state'),
            'yearlyTrend' => $this->trend($records, 4),
            'monthlyTrend' => $this->trend($records, 7),
            'records' => $records,
        ];
    }

    /** @param Collection<int, ProcurementStatisticRecord> $records
     * @return list<array{label: string, count: int, budgetCents: int, actualCents: int}>
     */
    private function group(Collection $records, string $field): array
    {
        return array_values($records->groupBy(fn ($row) => (string) ($row->{$field} ?: '未填写'))
            ->map(fn ($items, $label) => [
                'label' => (string) $label,
                'count' => $items->count(),
                'budgetCents' => (int) $items->sum('budget_cents'),
                'actualCents' => (int) $items->sum('actual_amount_cents'),
            ])
            ->sort(fn ($a, $b) => $b['count'] <=> $a['count'] ?: strcmp($a['label'], $b['label']))
            ->values()->all());
    }

    /** @param Collection<int, ProcurementStatisticRecord> $records
     * @return list<array{period: string, count: int, budgetCents: int, actualCents: int}>
     */
    private function trend(Collection $records, int $length): array
    {
        return array_values($records->filter(fn ($row) => $row->submitted_at !== null)
            ->groupBy(fn ($row) => substr($row->submitted_at, 0, $length))
            ->map(fn ($items, $period) => [
                'period' => (string) $period,
                'count' => $items->count(),
                'budgetCents' => (int) $items->sum('budget_cents'),
                'actualCents' => (int) $items->sum('actual_amount_cents'),
            ])
            ->sortKeys()->values()->all());
    }
}
