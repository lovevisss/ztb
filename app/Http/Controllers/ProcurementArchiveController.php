<?php

namespace App\Http\Controllers;

use App\Models\ProcurementApplication;
use App\Services\ProcurementAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementArchiveController extends Controller
{
    public function show(Request $request, ProcurementApplication $application, ProcurementAccess $access): Response
    {
        abort_unless($application->status === 'archived' && $access->canView($request->user(), $application), 403);

        return Inertia::render('procurement/ArchiveDetail', [
            'application' => $application,
            'archive' => DB::table('procurement_archives')->where('application_id', $application->id)->first(),
            'execution' => DB::table('procurement_executions')->where('application_id', $application->id)->first(),
            'approvalSteps' => DB::table('approval_steps')->where('application_id', $application->id)->orderBy('attempt')->orderBy('position')->get(),
            'approvalEvents' => DB::table('approval_events')->where('application_id', $application->id)->orderBy('id')->get(),
            'executionEvents' => DB::table('procurement_execution_events')->where('application_id', $application->id)->orderBy('id')->get(),
            'attachments' => DB::table('procurement_attachments')->where('application_id', $application->id)->orderBy('id')->get(['id', 'kind', 'original_name', 'size', 'mime_type']),
        ]);
    }

    public function store(Request $request, ProcurementApplication $application, ProcurementAccess $access): RedirectResponse
    {
        abort_unless($application->status === 'execution_completed' && $access->canExecute($request->user(), $application), 403);

        DB::transaction(function () use ($request, $application, $access) {
            $locked = ProcurementApplication::query()->lockForUpdate()->findOrFail($application->id);
            abort_unless($locked->status === 'execution_completed' && $access->canExecute($request->user(), $locked), 403);
            $executionComplete = DB::table('procurement_executions')->where('application_id', $locked->id)
                ->where('status', 'completed')->exists();
            abort_unless($executionComplete, 403);

            $requiredKinds = DB::table('procurement_archive_rules')
                ->where('application_form', $locked->application_form)->where('required', true)
                ->orderBy('id')->pluck('kind')->all();
            $attachments = DB::table('procurement_attachments')->where('application_id', $locked->id)->get();
            $available = $attachments->filter(fn ($file) => Storage::disk($file->disk)->exists($file->path));
            $presentKinds = $available->pluck('kind')->unique()->all();
            $missing = array_values(array_diff($requiredKinds, $presentKinds));
            if ($missing !== []) {
                throw ValidationException::withMessages(['materials' => '缺少必需归档材料：'.implode('、', $missing)]);
            }

            DB::table('procurement_archives')->insert([
                'application_id' => $locked->id,
                'archived_by' => $request->user()->id,
                'required_kinds_snapshot' => json_encode($requiredKinds, JSON_UNESCAPED_UNICODE),
                'attachment_ids_snapshot' => json_encode($available->pluck('id')->all()),
                'archived_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $locked->update(['status' => 'archived']);
            DB::table('procurement_execution_events')->insert([
                'application_id' => $locked->id, 'actor_id' => $request->user()->id,
                'event' => 'archived', 'details' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return to_route('procurement.archives.show', $application->id);
    }
}
