<?php

namespace App\Http\Controllers;

use App\Services\LegacyApprovalTimeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LegacyProcurementController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'between:2021,2100'],
        ]);
        $query = DB::table('legacy_procurement_records')->where('source_table', 'zcy_cgsq');
        if (! $user->hasAnyRole(['procurement_manager', 'system_admin'])) {
            $query->where(function ($scope) use ($user) {
                $scope->where('user_id', $user->id);
                if ($user->department_id !== null) {
                    $scope->orWhere('department_id', $user->department_id);
                }
            });
        }
        $query->when($filters['search'] ?? null, fn ($scope, $search) => $scope->where(function ($nested) use ($search) {
            $nested->where('title', 'like', '%'.$search.'%')->orWhere('number', 'like', '%'.$search.'%');
        }))->when($filters['year'] ?? null, fn ($scope, $year) => $scope->whereYear('occurred_at', $year));

        $summary = (clone $query)->selectRaw('COUNT(*) as projects_count, COALESCE(SUM(amount_cents), 0) as budget_cents')->first();
        $yearExpression = DB::getDriverName() === 'sqlite' ? "CAST(strftime('%Y', occurred_at) AS INTEGER)" : 'YEAR(occurred_at)';
        $byYear = (clone $query)->selectRaw("{$yearExpression} as year, COUNT(*) as projects_count, COALESCE(SUM(amount_cents), 0) as budget_cents")
            ->groupByRaw($yearExpression)->orderByDesc('year')->get();

        return Inertia::render('procurement/LegacyHistory', [
            'summary' => $summary,
            'byYear' => $byYear,
            'projects' => $query->orderByDesc('occurred_at')->paginate(20, [
                'source_id', 'number', 'title', 'status', 'amount_cents', 'occurred_at',
                'creator_name', 'department_code',
            ])->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, string $sourceId, LegacyApprovalTimeline $timeline): Response
    {
        $project = DB::table('legacy_procurement_records')->where('source_table', 'zcy_cgsq')
            ->where('source_id', $sourceId)->first();
        abort_if($project === null, 404);
        $user = $request->user();
        abort_unless($user->hasAnyRole(['procurement_manager', 'system_admin'])
            || $project->user_id === $user->id
            || ($user->department_id !== null && $project->department_id === $user->department_id), 403);

        $records = DB::table('legacy_procurement_records')->where('application_source_id', $sourceId)
            ->where('source_table', '!=', 'zcy_cgsq')
            ->orderBy('occurred_at')->orderBy('source_id')
            ->get(['source_table', 'source_id', 'number', 'title', 'status', 'amount_cents', 'occurred_at', 'details']);

        return Inertia::render('procurement/LegacyDetail', [
            'project' => $project,
            'records' => $records,
            'workflowNodes' => $timeline->nodes($project, $records),
            'attachments' => DB::table('legacy_procurement_attachments')->where('application_source_id', $sourceId)
                ->get(['owner_table', 'owner_source_id', 'name', 'migration_status']),
        ]);
    }
}
