<?php

namespace App\Http\Controllers;

use App\Models\ProcurementClassificationRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        abort(410, '采购分类分界金额现固定为10万元，历史规则仅供查阅。');

    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole(['procurement_manager', 'system_admin']), 403);
    }
}
