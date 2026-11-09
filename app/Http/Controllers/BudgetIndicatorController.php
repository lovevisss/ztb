<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BudgetIndicatorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'string', 'max:32'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = DB::table('budget_indicators')
            ->leftJoin('departments', 'departments.external_code', '=', 'budget_indicators.department_code')
            ->select([
                'budget_indicators.id', 'budget_indicators.code', 'budget_indicators.name',
                'budget_indicators.director', 'budget_indicators.department_code',
                'departments.name as department_name', 'budget_indicators.status',
                'budget_indicators.available_cents',
            ]);
        if (filled($filters['q'] ?? null)) {
            $term = trim($filters['q']);
            $query->where(function ($nested) use ($term) {
                $nested->where('budget_indicators.name', 'like', '%'.$term.'%')
                    ->orWhere('budget_indicators.code', 'like', '%'.$term.'%');
            });
        }
        if (filled($filters['department'] ?? null)) {
            $query->where('budget_indicators.department_code', $filters['department']);
        }
        if (filled($filters['status'] ?? null)) {
            $filters['status'] === '__null'
                ? $query->whereNull('budget_indicators.status')
                : $query->where('budget_indicators.status', $filters['status']);
        }
        $page = $query->orderBy('budget_indicators.name')->orderBy('budget_indicators.id')->paginate(25);
        $departments = DB::table('budget_indicators')
            ->leftJoin('departments', 'departments.external_code', '=', 'budget_indicators.department_code')
            ->whereNotNull('budget_indicators.department_code')
            ->where('budget_indicators.department_code', '!=', '')
            ->distinct()->orderBy('budget_indicators.department_code')
            ->get(['budget_indicators.department_code as code', 'departments.name']);
        $statuses = DB::table('budget_indicators')->distinct()->orderBy('status')->pluck('status');

        return response()->json([
            'data' => $page->items(), 'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(), 'total' => $page->total(),
            'departments' => $departments, 'statuses' => $statuses,
        ]);
    }
}
