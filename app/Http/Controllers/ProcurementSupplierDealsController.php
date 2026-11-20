<?php

namespace App\Http\Controllers;

use App\Models\ProcurementSupplier;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementSupplierDealsController extends Controller
{
    private const CONTENT_FIELDS = [
        'projects.title', 'projects.procurement_requirements',
        'engineering.project_name', 'engineering.construction_content',
    ];

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);
        $filters = $request->validate([
            'keyword' => ['nullable', 'string', 'max:200'],
            'supplier' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
        $keyword = trim($filters['keyword'] ?? '');
        $name = trim($filters['supplier'] ?? '');
        $status = $filters['status'] ?? '';

        $matched = $this->matchingDeals($keyword);
        $counts = (clone $matched)->select('deals.supplier_id')->selectRaw('count(*) as matched_count')
            ->groupBy('deals.supplier_id');
        $totals = $this->deals()->select('deals.supplier_id')->selectRaw('count(*) as project_count')
            ->groupBy('deals.supplier_id');

        $suppliers = DB::table('procurement_suppliers as suppliers')
            ->joinSub($counts, 'matches', 'matches.supplier_id', '=', 'suppliers.id')
            ->joinSub($totals, 'totals', 'totals.supplier_id', '=', 'suppliers.id')
            ->when($name !== '', fn (Builder $query) => $this->whereContains($query, ['suppliers.name'], $name))
            ->when($status !== '', fn (Builder $query) => $query->where('suppliers.is_active', $status === 'active'))
            ->orderByDesc('matches.matched_count')->orderBy('suppliers.name')->orderBy('suppliers.id')
            ->paginate(20, [
                'suppliers.id', 'suppliers.name', 'suppliers.contact_name', 'suppliers.contact_phone',
                'suppliers.is_active', 'matches.matched_count', 'totals.project_count',
            ])->withQueryString();

        $supplierIds = $suppliers->getCollection()->pluck('id');
        $previews = $supplierIds->isEmpty() ? collect() : $this->selectProjectContent($matched)
            ->whereIn('deals.supplier_id', $supplierIds)->orderByDesc('deals.completed_on')->orderByDesc('projects.id')
            ->groupLimit(3, 'deals.supplier_id')->get();
        $items = $this->items($previews);
        $previews->each(function ($project) use ($items, $keyword): void {
            $project->contents = $this->previewContent($project, $items->get($project->id, collect()), $keyword);
        });
        $bySupplier = $previews->groupBy('supplier_id');
        $suppliers->through(fn ($supplier) => [
            'id' => $supplier->id, 'name' => $supplier->name, 'contact_name' => $supplier->contact_name,
            'contact_phone' => $supplier->contact_phone, 'is_active' => (bool) $supplier->is_active,
            'matched_count' => (int) $supplier->matched_count, 'project_count' => (int) $supplier->project_count,
            'contents' => $bySupplier->get($supplier->id, collect())->flatMap(fn ($project) => $project->contents)
                ->unique()->take(3)->values()->all(),
        ]);

        return Inertia::render('procurement/SupplierDeals', [
            'suppliers' => $suppliers,
            'filters' => ['keyword' => $keyword, 'supplier' => $name, 'status' => $status],
        ]);
    }

    public function show(Request $request, ProcurementSupplier $supplier): Response
    {
        $this->authorizeAccess($request);
        $projects = $this->selectProjectContent($this->matchingDeals(''))
            ->leftJoin('departments', 'departments.id', '=', 'projects.department_id')
            ->where('deals.supplier_id', $supplier->id)
            ->addSelect('projects.project_number', 'projects.status', 'departments.name as department_name',
                'deals.completed_on', 'deals.actual_amount_cents')
            ->orderByDesc('deals.completed_on')->orderByDesc('projects.id')->paginate(20);
        $items = $this->items($projects->getCollection());
        $projects->through(function ($project) use ($items) {
            $project->items = $items->get($project->id, collect())->map(fn ($item) => [
                'id' => $item->id, 'name' => $item->name, 'specification' => $item->specification,
            ])->values();

            return $project;
        });

        return Inertia::render('procurement/SupplierDealHistory', [
            'supplier' => $supplier->only(['id', 'name', 'contact_name', 'contact_phone', 'is_active']),
            'projects' => $projects,
        ]);
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole(['procurement_manager', 'system_admin']), 403);
    }

    private function deals(): Builder
    {
        return DB::table('procurement_executions as deals')
            ->join('procurement_applications as projects', 'projects.id', '=', 'deals.application_id')
            ->whereNotNull('deals.supplier_id')
            ->where('deals.status', 'completed')
            ->where('projects.application_form', 'self_purchase')
            ->whereIn('projects.status', ['execution_completed', 'archived']);
    }

    private function matchingDeals(string $keyword): Builder
    {
        return $this->deals()->leftJoin('procurement_engineering_details as engineering', 'engineering.application_id', '=', 'projects.id')
            ->when($keyword !== '', fn (Builder $query) => $query->where(function (Builder $search) use ($keyword): void {
                $this->whereContains($search, self::CONTENT_FIELDS, $keyword)
                    ->orWhereExists(function (Builder $items) use ($keyword): void {
                        $items->selectRaw('1')->from('procurement_line_items as items')
                            ->whereColumn('items.application_id', 'projects.id');
                        $this->whereContains($items, ['items.name', 'items.specification'], $keyword);
                    });
            }));
    }

    private function whereContains(Builder $query, array $columns, string $keyword): Builder
    {
        $pattern = '%'.strtr($keyword, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';

        return $query->where(function (Builder $matches) use ($columns, $pattern): void {
            foreach ($columns as $column) {
                $matches->orWhereRaw("lower({$column}) like lower(?) escape '!'", [$pattern]);
            }
        });
    }

    private function selectProjectContent(Builder $query): Builder
    {
        return $query->select('projects.id', 'deals.supplier_id', ...self::CONTENT_FIELDS);
    }

    private function items(Collection $projects): Collection
    {
        if ($projects->isEmpty()) {
            return collect();
        }

        return DB::table('procurement_line_items')->whereIn('application_id', $projects->pluck('id'))
            ->orderBy('position')->orderBy('id')->get(['id', 'application_id', 'name', 'specification'])->groupBy('application_id');
    }

    private function previewContent(object $project, Collection $items, string $keyword): array
    {
        $content = $items->map(fn ($item) => $item->specification ? $item->name.' · '.$item->specification : $item->name)->all();
        $content = array_filter([...$content, $project->project_name, $project->construction_content, $project->title, $project->procurement_requirements]);
        if ($keyword !== '') {
            $matches = array_filter($content, fn ($text) => mb_stripos($text, $keyword) !== false);
            $content = $matches ?: array_slice($content, 0, 1);
        }

        return array_map(function ($text) use ($keyword) {
            $text = Str::squish($text);
            $offset = $keyword !== '' ? mb_stripos($text, $keyword) : false;
            $start = $offset === false ? 0 : max(0, $offset - 24);

            return ($start > 0 ? '…' : '').Str::limit(mb_substr($text, $start), 100, '…');
        }, array_slice($content, 0, 3));
    }
}
