<?php

namespace App\Http\Controllers;

use App\Services\ProcurementSpreadsheet;
use App\Services\ProcurementStatistics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProcurementStatisticsController extends Controller
{
    public function index(Request $request, ProcurementStatistics $statistics): Response
    {
        $filters = $this->filters($request);
        $records = $statistics->records($request->user(), $filters);

        return Inertia::render('procurement/Statistics', [
            ...$statistics->dashboard($records),
            'filters' => $filters,
            'departments' => $request->user()->hasAnyRole(['procurement_manager', 'system_admin'])
                ? DB::table('departments')->orderBy('name')->get(['id', 'name'])
                : DB::table('departments')->where('id', $request->user()->department_id)->get(['id', 'name']),
        ]);
    }

    public function export(Request $request, ProcurementStatistics $statistics, ProcurementSpreadsheet $spreadsheet): BinaryFileResponse
    {
        $records = $statistics->records($request->user(), $this->filters($request));
        $path = $spreadsheet->write($records);

        return response()->download($path, '采购统计-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'responsible_department' => ['nullable', 'string', 'max:255'],
            'purchase_method' => ['nullable', 'string', 'max:255'],
            'organization_form' => ['nullable', 'string', 'max:255'],
            'funding_source' => ['nullable', 'string', 'max:255'],
            'purchase_type' => ['nullable', Rule::in(['self_purchase', 'central_tender'])],
            'status' => ['nullable', Rule::in(['submitted', 'returned', 'rejected', 'approved', 'executing', 'execution_completed', 'archived'])],
            'execution_status' => ['nullable', Rule::in(['not_started', 'draft', 'completed'])],
            'archive_status' => ['nullable', Rule::in(['archived', 'not_archived'])],
        ]);
    }
}
