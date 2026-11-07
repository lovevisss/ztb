<?php

namespace App\Http\Controllers;

use App\Models\ProcurementApplication;
use App\Services\ProcurementAccess;
use App\Services\TenderNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementHistoryController extends Controller
{
    public function index(Request $request, ProcurementAccess $access): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'purchase_method' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['submitted', 'returned', 'rejected', 'approved', 'executing', 'execution_completed', 'archived'])],
        ]);

        $projects = $access->visibleHistoryQuery($request->user())
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('title', 'like', '%'.$search.'%'))
            ->when($filters['year'] ?? null, fn ($query, $year) => $query->whereYear('submitted_at', $year))
            ->when($filters['purchase_method'] ?? null, fn ($query, $method) => $query->where('purchase_method', $method))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('submitted_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('procurement/History', [
            'projects' => $projects,
            'filters' => $filters,
            'canManageArchiveRules' => $request->user()->hasAnyRole(['procurement_manager', 'system_admin']),
        ]);
    }

    public function copy(Request $request, ProcurementApplication $application, ProcurementAccess $access, TenderNumber $numbers): RedirectResponse
    {
        abort_unless($application->status !== 'draft' && $access->canView($request->user(), $application), 403);
        abort_if($request->user()->department_id === null, 422, '当前用户尚未关联部门');

        $copy = DB::transaction(function () use ($request, $application, $numbers) {
            $copy = ProcurementApplication::create([
                ...$application->only([
                    'title', 'budget_cents', 'funding_source', 'organization_form', 'purchase_method',
                    'reason', 'responsible_department', 'is_fixed_asset', 'application_form',
                    'project_leader', 'procurement_requirements', 'purchase_list',
                    'technical_parameters', 'service_requirements', 'acceptance_criteria', 'budget_basis_note',
                ]),
                'applicant_id' => $request->user()->id,
                'department_id' => $request->user()->department_id,
                'source_application_id' => $application->id,
                'status' => 'draft',
            ]);

            $copy->update([
                'project_number' => $copy->application_form === 'central_tender'
                    ? $numbers->next()
                    : 'CG'.now()->format('Y').'-'.str_pad((string) $copy->id, 6, '0', STR_PAD_LEFT),
            ]);

            return $copy;
        });

        return $copy->application_form === 'central_tender'
            ? to_route('procurement.tenders.edit', $copy->id)
            : to_route('procurement.applications.edit', $copy->id);
    }
}
