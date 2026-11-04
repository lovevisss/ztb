<?php

namespace App\Http\Controllers;

use App\Models\ProcurementClassificationRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementClassificationRuleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeManager($request);

        $rules = ProcurementClassificationRule::query()->orderByDesc('effective_at')->orderByDesc('id')->get();
        $activeScopes = [];
        $viewRules = [];

        foreach ($rules as $rule) {
            $scopeKey = $rule->scope.'-'.$rule->department_id;
            $scheduled = $rule->effective_at->isFuture();
            $active = ! $scheduled && ! isset($activeScopes[$scopeKey]);
            if ($active) {
                $activeScopes[$scopeKey] = true;
            }

            $viewRules[] = [
                'id' => $rule->id,
                'threshold_cents' => $rule->threshold_cents,
                'scope' => $rule->scope,
                'department_id' => $rule->department_id,
                'effective_at' => $rule->effective_at->format('Y-m-d H:i:s'),
                'is_active' => $active,
                'is_scheduled' => $scheduled,
            ];
        }

        return Inertia::render('procurement/ClassificationRules', [
            'rules' => $viewRules,
            'departments' => DB::table('departments')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManager($request);

        $data = $request->validate([
            'threshold_yuan' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'scope' => ['required', Rule::in(['global', 'department'])],
            'department_id' => ['required_if:scope,department', 'prohibited_if:scope,global', 'exists:departments,id'],
            'effective_at' => ['required', 'date'],
        ]);

        ProcurementClassificationRule::create([
            'threshold_cents' => (int) round((float) $data['threshold_yuan'] * 100),
            'scope' => $data['scope'],
            'department_id' => $data['scope'] === 'department' ? $data['department_id'] : null,
            'effective_at' => $data['effective_at'],
            'created_by' => $request->user()->id,
        ]);

        return to_route('procurement.classification-rules.index');
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole(['procurement_manager', 'system_admin']), 403);
    }
}
