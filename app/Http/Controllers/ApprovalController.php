<?php

namespace App\Http\Controllers;

use App\Models\ApprovalStep;
use App\Models\ProcurementApplication;
use App\Services\ProcurementAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function progress(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(['procurement_manager', 'system_admin']), 403);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim($filters['search'] ?? '');
        $projects = DB::table('procurement_applications as applications')
            ->join('users as applicants', 'applicants.id', '=', 'applications.applicant_id')
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('approval_steps')
                ->whereColumn('approval_steps.application_id', 'applications.id'))
            ->when($search !== '', fn ($query) => $query->where(function ($matches) use ($search) {
                $matches->where('applications.project_number', 'like', '%'.$search.'%')
                    ->orWhere('applications.title', 'like', '%'.$search.'%')
                    ->orWhere('applicants.name', 'like', '%'.$search.'%');
            }))
            ->orderByDesc('applications.submitted_at')->orderByDesc('applications.id')
            ->select(['applications.id', 'applications.project_number', 'applications.title', 'applications.status',
                'applications.submitted_at', 'applicants.name as applicant_name'])
            ->paginate(15)->withQueryString();

        $steps = DB::table('approval_steps')->leftJoin('users as assignees', 'assignees.id', '=', 'approval_steps.assignee_id')
            ->whereIn('approval_steps.application_id', $projects->getCollection()->pluck('id'))
            ->orderByDesc('approval_steps.attempt')->orderBy('approval_steps.position')
            ->get(['approval_steps.application_id', 'approval_steps.attempt', 'approval_steps.role_code',
                'approval_steps.status', 'assignees.name as assignee_name'])
            ->groupBy('application_id');
        $projects->getCollection()->transform(function ($project) use ($steps) {
            $latest = $steps->get($project->id, collect());
            $attempt = $latest->first()?->attempt;
            $current = $project->status === 'submitted'
                ? ($latest->first(fn ($step) => $step->attempt === $attempt && $step->status === 'pending')
                    ?? $latest->first(fn ($step) => $step->attempt === $attempt && $step->status === 'queued'))
                : null;
            $project->attempt = $attempt;
            $project->current_role_code = $current?->role_code;
            $project->current_assignee_name = $current?->assignee_name;
            $project->current_step_status = $current?->status;

            return $project;
        });

        return Inertia::render('procurement/ApprovalProgress', [
            'projects' => $projects,
            'filters' => ['search' => $search],
        ]);
    }

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
            'workflowReturnUrl' => $request->user()->hasAnyRole(['procurement_manager', 'system_admin'])
                && $request->query('from') === 'workflow'
                ? route('procurement.approvals.progress', array_filter([
                    'search' => $request->query('search'), 'page' => $request->query('page'),
                ])) : null,
            'application' => $application,
            'applicantName' => DB::table('users')->where('id', $application->applicant_id)->value('name'),
            'applicantDepartmentName' => DB::table('departments')->where('id', $application->department_id)->value('name'),
            'sourceApplication' => $source === null ? null : [
                'id' => $source->id,
                'project_number' => $source->project_number,
                'title' => $source->title,
                'canView' => $access->canView($request->user(), $source),
            ],
            'steps' => DB::table('approval_steps')->leftJoin('users', 'users.id', '=', 'approval_steps.assignee_id')
                ->where('approval_steps.application_id', $application->id)
                ->orderBy('approval_steps.attempt')->orderBy('approval_steps.position')
                ->get(['approval_steps.*', 'users.name as assignee_name']),
            'events' => DB::table('approval_events')->leftJoin('users', 'users.id', '=', 'approval_events.actor_id')
                ->where('approval_events.application_id', $application->id)->orderBy('approval_events.id')
                ->get(['approval_events.*', 'users.name as actor_name']),
            'attachments' => DB::table('procurement_attachments')->where('application_id', $application->id)->get(['id', 'kind', 'original_name', 'mime_type']),
            'lineItems' => DB::table('procurement_line_items')->where('application_id', $application->id)->orderBy('position')->get(),
            'engineering' => DB::table('procurement_engineering_details')->where('application_id', $application->id)->first(),
            'budgetAllocations' => DB::table('procurement_budget_allocations')->where('application_id', $application->id)->get(),
            'usageDepartmentName' => $application->usage_department_id === null ? null : DB::table('departments')->where('id', $application->usage_department_id)->value('name'),
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
                ? DB::table('users')->where('is_active', true)->orderBy('name')->get(['id', 'name']) : [],
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
        $data = $request->validate(['assignee_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)]]);

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

        return to_route('procurement.applications.show', [
            'application' => $step->application_id,
            ...($request->query('from') === 'workflow' ? ['from' => 'workflow',
                'search' => $request->query('search'), 'page' => $request->query('page')] : []),
        ]);
    }
}
