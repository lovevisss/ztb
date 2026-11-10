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
            ->where('source', 'school_source')
            ->select([
                'id', 'code', 'name', 'director', 'department_code',
                'department_name', 'status', 'available_cents',
            ]);
        if (filled($filters['q'] ?? null)) {
            $term = trim($filters['q']);
            $query->where(function ($nested) use ($term) {
                $nested->where('name', 'like', '%'.$term.'%')
                    ->orWhere('code', 'like', '%'.$term.'%');
            });
        }
        if (filled($filters['department'] ?? null)) {
            $query->where('department_code', $filters['department']);
        }
        if (filled($filters['status'] ?? null)) {
            $filters['status'] === '__null'
                ? $query->whereNull('status')
                : $query->where('status', $filters['status']);
        }
        $page = $query->orderBy('name')->orderBy('id')->paginate(25);
        $departments = DB::table('budget_indicators')
            ->where('source', 'school_source')
            ->whereNotNull('department_code')->where('department_code', '!=', '')
            ->selectRaw('department_code as code, MAX(department_name) as name')
            ->groupBy('department_code')->orderBy('department_code')->get();
        $statuses = DB::table('budget_indicators')->where('source', 'school_source')
            ->distinct()->orderBy('status')->pluck('status');

        return response()->json([
            'data' => $page->items(), 'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(), 'total' => $page->total(),
            'source_total' => DB::table('budget_indicators')->where('source', 'school_source')->count(),
            'departments' => $departments, 'statuses' => $statuses,
        ]);
    }
}
