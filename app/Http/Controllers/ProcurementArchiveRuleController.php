<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementArchiveRuleController extends Controller
{
    private const KINDS = ['contract', 'result_announcement', 'acceptance', 'invoice', 'other_execution'];

    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(['system_admin', 'procurement_manager']), 403);

        return Inertia::render('procurement/ArchiveRules', [
            'rules' => DB::table('procurement_archive_rules')->orderBy('application_form')->orderBy('id')->get(),
            'kinds' => self::KINDS,
            'canEdit' => $request->user()->hasAnyRole(['system_admin']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['system_admin']), 403);
        $data = $request->validate([
            'application_form' => ['required', Rule::in(['self_purchase', 'central_tender'])],
            'required_kinds' => ['present', 'array'],
            'required_kinds.*' => ['string', Rule::in(self::KINDS), 'distinct'],
        ]);

        DB::transaction(function () use ($request, $data) {
            foreach (self::KINDS as $kind) {
                DB::table('procurement_archive_rules')
                    ->where('application_form', $data['application_form'])
                    ->where('kind', $kind)
                    ->update([
                        'required' => in_array($kind, $data['required_kinds'], true),
                        'updated_by' => $request->user()->id,
                        'updated_at' => now(),
                    ]);
            }
        });
        $request->attributes->set('audit_subject', ['type' => 'archive_rule', 'id' => $data['application_form'],
            'label' => $data['application_form'] === 'central_tender' ? '统一招标归档规则' : '部门自行采购归档规则']);

        return to_route('procurement.archive-rules.index');
    }
}
