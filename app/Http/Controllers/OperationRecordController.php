<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OperationRecordController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(['system_admin']), 403);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(['login', 'procurement', 'approval', 'execution', 'configuration', 'account'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $search = trim($filters['search'] ?? '');
        $records = DB::table('operation_records')
            ->when($search !== '', fn ($query) => $query->where(function ($match) use ($search) {
                $match->where('actor_name', 'like', '%'.$search.'%')
                    ->orWhere('subject_label', 'like', '%'.$search.'%')
                    ->orWhere('subject_id', 'like', '%'.$search.'%');
            }))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(30)->withQueryString();

        return Inertia::render('admin/OperationRecords', [
            'records' => $records,
            'filters' => [...$filters, 'search' => $search],
        ]);
    }
}
