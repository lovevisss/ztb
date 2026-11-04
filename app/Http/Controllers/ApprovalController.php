<?php

namespace App\Http\Controllers;

use App\Models\ApprovalStep;
use App\Models\ProcurementApplication;
use App\Services\ProcurementAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function inbox(Request $request): Response
    {
        return Inertia::render('procurement/ApprovalInbox', [
            'steps' => DB::table('approval_steps')
                ->join('procurement_applications', 'procurement_applications.id', '=', 'approval_steps.application_id')
                ->where('approval_steps.assignee_id', $request->user()->id)
                ->where('approval_steps.status', 'pending')
                ->orderBy('approval_steps.created_at')
                ->get(['approval_steps.id', 'approval_steps.role_code', 'approval_steps.application_id', 'procurement_applications.title', 'procurement_applications.project_number', 'procurement_applications.budget_cents']),
        ]);
    }

    public function show(Request $request, ProcurementApplication $application, ProcurementAccess $access): Response
    {
        abort_unless($access->canView($request->user(), $application), 403);

        $source = $application->source_application_id === null
            ? null : ProcurementApplication::query()->find($application->source_application_id);

        return Inertia::render('procurement/ApprovalDetail', [
            'application' => $application,
            'sourceApplication' => $source === null ? null : [
                'id' => $source->id,
                'project_number' => $source->project_number,
                'title' => $source->title,
                'canView' => $access->canView($request->user(), $source),
            ],
            'steps' => DB::table('approval_steps')->where('application_id', $application->id)->orderBy('attempt')->orderBy('position')->get(),
            'events' => DB::table('approval_events')->where('application_id', $application->id)->orderBy('id')->get(),
            'attachments' => DB::table('procurement_attachments')->where('application_id', $application->id)->get(['id', 'kind', 'original_name']),
            'execution' => DB::table('procurement_executions')->where('application_id', $application->id)->first(),
            'canArchive' => $application->status === 'execution_completed' && $access->canExecute($request->user(), $application),
            'missingArchiveMaterials' => $application->status === 'execution_completed'
                ? array_values(array_diff(
                    DB::table('procurement_archive_rules')->where('application_form', $application->application_form)->where('required', true)->pluck('kind')->all(),
                    DB::table('procurement_attachments')->where('application_id', $application->id)->pluck('kind')->all(),
                )) : [],
            'currentUserId' => $request->user()->id,
            'canManageApprovals' => $request->user()->hasAnyRole(['procurement_manager', 'system_admin']),
            'users' => $request->user()->hasAnyRole(['procurement_manager', 'system_admin'])
                ? DB::table('users')->orderBy('name')->get(['id', 'name']) : [],
        ]);
    }

    public function decide(Request $request, ApprovalStep $step): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:approve,return,reject'],
            'opinion' => ['required_if:action,return,reject', 'nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $step, $data) {
            $locked = ApprovalStep::query()->lockForUpdate()->findOrFail($step->id);
            abort_unless($locked->status === 'pending' && $locked->assignee_id === $request->user()->id, 403);

            $application = ProcurementApplication::query()->lockForUpdate()->findOrFail($locked->application_id);
            abort_unless($application->status === 'submitted' && $application->approval_attempt === $locked->attempt, 403);

            $locked->update([
                'status' => $data['action'] === 'approve' ? 'approved' : ($data['action'] === 'return' ? 'returned' : 'rejected'),
                'opinion' => $data['opinion'] ?? null,
                'acted_at' => now(),
            ]);

            if ($data['action'] === 'approve') {
                $next = ApprovalStep::query()->where('application_id', $application->id)
                    ->where('attempt', $locked->attempt)->where('position', '>', $locked->position)
                    ->orderBy('position')->first();
                if ($next !== null) {
                    $next->update(['status' => 'pending']);
                } else {
                    $application->update(['status' => 'approved']);
                }
            } else {
                ApprovalStep::query()->where('application_id', $application->id)
                    ->where('attempt', $locked->attempt)->where('status', 'queued')
                    ->update(['status' => 'cancelled']);
                $application->update(['status' => $data['action'] === 'return' ? 'returned' : 'rejected']);
            }

            DB::table('approval_events')->insert([
                'application_id' => $application->id,
                'step_id' => $locked->id,
                'actor_id' => $request->user()->id,
                'event' => $data['action'] === 'approve' ? 'approved' : ($data['action'] === 'return' ? 'returned' : 'rejected'),
                'details' => $data['opinion'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return to_route('procurement.applications.show', $step->application_id);
    }

    public function reassign(Request $request, ApprovalStep $step): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['procurement_manager', 'system_admin']), 403);
        $data = $request->validate(['assignee_id' => ['required', 'integer', 'exists:users,id']]);

        DB::transaction(function () use ($request, $step, $data) {
            $locked = ApprovalStep::query()->lockForUpdate()->findOrFail($step->id);
            abort_unless(in_array($locked->status, ['pending', 'queued'], true), 403);
            $oldAssignee = $locked->assignee_id;
            $locked->update(['assignee_id' => $data['assignee_id']]);

            DB::table('approval_events')->insert([
                'application_id' => $locked->application_id,
                'step_id' => $locked->id,
                'actor_id' => $request->user()->id,
                'event' => 'reassigned',
                'details' => "审批人由 {$oldAssignee} 调整为 {$data['assignee_id']}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return to_route('procurement.applications.show', $step->application_id);
    }
}
