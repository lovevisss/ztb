<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementProjectOverviewController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(['procurement_manager', 'system_admin']), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', Rule::in(['new', 'legacy'])],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
        ]);
        $search = trim($filters['search'] ?? '');

        $new = DB::table('procurement_applications as applications')
            ->join('users as applicants', 'applicants.id', '=', 'applications.applicant_id')
            ->leftJoin('departments', 'departments.id', '=', 'applications.department_id')
            ->where('applications.status', '!=', 'draft')
            ->selectRaw("'new' as source")
            ->addSelect([
                'applications.id as source_id', 'applications.project_number as number',
                'applications.title', 'applications.department_id', 'departments.name as department_name',
                'applicants.name as applicant_name', 'applications.budget_cents',
                'applications.status', 'applications.submitted_at as project_date',
            ]);
        $legacy = DB::table('legacy_procurement_records as records')
            ->leftJoin('departments', 'departments.id', '=', 'records.department_id')
            ->where('records.source_table', 'zcy_cgsq')
            ->where('records.status', '!=', '未提交')
            ->selectRaw("'legacy' as source")
            ->addSelect([
                'records.source_id', 'records.number', 'records.title', 'records.department_id',
                'departments.name as department_name', 'records.creator_name as applicant_name',
                'records.amount_cents as budget_cents', 'records.status', 'records.occurred_at as project_date',
            ]);

        $projects = DB::query()->fromSub($new->unionAll($legacy), 'projects')
            ->when($search !== '', fn ($query) => $query->where(function ($matches) use ($search) {
                $matches->where('number', 'like', '%'.$search.'%')
                    ->orWhere('title', 'like', '%'.$search.'%')
                    ->orWhere('applicant_name', 'like', '%'.$search.'%');
            }))
            ->when($filters['source'] ?? null, fn ($query, $source) => $query->where('source', $source))
            ->when($filters['year'] ?? null, fn ($query, $year) => $query->whereYear('project_date', $year))
            ->when($filters['department_id'] ?? null, fn ($query, $department) => $query->where('department_id', $department))
            ->orderByDesc('project_date')->orderByDesc('source_id')
            ->paginate(20)->withQueryString();

        return Inertia::render('procurement/ProjectOverview', [
            'projects' => $projects,
            'filters' => $filters,
            'departments' => DB::table('departments')->orderBy('name')->get(['id', 'name']),
            'totals' => [
                'new' => DB::table('procurement_applications')->where('status', '!=', 'draft')->count(),
                'legacy' => DB::table('legacy_procurement_records')->where('source_table', 'zcy_cgsq')
                    ->where('status', '!=', '未提交')->count(),
            ],
        ]);
    }
}
