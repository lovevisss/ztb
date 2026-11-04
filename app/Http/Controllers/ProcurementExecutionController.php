<?php

namespace App\Http\Controllers;

use App\Models\ProcurementApplication;
use App\Models\ProcurementAttachment;
use App\Models\ProcurementExecution;
use App\Services\ProcurementAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProcurementExecutionController extends Controller
{
    public function show(Request $request, ProcurementApplication $application, ProcurementAccess $access): Response
    {
        abort_unless(in_array($application->status, ['approved', 'executing', 'execution_completed', 'archived'], true), 403);
        abort_unless($access->canView($request->user(), $application), 403);

        return Inertia::render('procurement/ExecutionForm', [
            'application' => $application,
            'execution' => ProcurementExecution::query()->where('application_id', $application->id)->first(),
            'attachments' => ProcurementAttachment::query()->where('application_id', $application->id)
                ->whereIn('kind', ['contract', 'result_announcement', 'acceptance', 'invoice', 'other_execution'])
                ->get(['id', 'kind', 'original_name', 'size']),
            'canEdit' => $application->status !== 'archived' && $access->canExecute($request->user(), $application),
        ]);
    }

    public function save(Request $request, ProcurementApplication $application, ProcurementAccess $access): RedirectResponse
    {
        $this->authorizeEditable($request, $application, $access);

        $intent = $request->validate(['intent' => ['required', 'in:draft,complete']])['intent'];
        $required = $intent === 'complete' || $application->status === 'execution_completed' ? 'required' : 'nullable';
        $data = $request->validate([
            'actual_amount' => [$required, 'numeric', 'min:0.01', 'decimal:0,2'],
            'supplier' => [$required, 'string', 'max:255'],
            'completed_on' => [$required, 'date_format:Y-m-d'],
            'result_note' => [$required, 'string'],
            'contract_document' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:20480'],
            'result_announcement_document' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:20480'],
            'acceptance_document' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:20480'],
            'invoice_document' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:20480'],
            'other_execution_documents' => ['nullable', 'array', 'max:10'],
            'other_execution_documents.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:20480'],
        ]);

        $data['actual_amount_cents'] = isset($data['actual_amount']) ? (int) round((float) $data['actual_amount'] * 100) : null;
        unset(
            $data['actual_amount'], $data['contract_document'], $data['result_announcement_document'],
            $data['acceptance_document'], $data['invoice_document'], $data['other_execution_documents']
        );

        $storedPaths = [];
        try {
            DB::transaction(function () use ($request, $application, $access, $intent, $data, &$storedPaths) {
                $locked = ProcurementApplication::query()->lockForUpdate()->findOrFail($application->id);
                $this->authorizeEditable($request, $locked, $access);

                $execution = ProcurementExecution::query()->firstOrNew(['application_id' => $locked->id]);
                $completed = $intent === 'complete' || $locked->status === 'execution_completed';
                $execution->fill([
                    ...$data,
                    'status' => $completed ? 'completed' : 'draft',
                    'registered_at' => $completed ? ($execution->registered_at ?? now()) : null,
                ]);
                $execution->save();

                $this->storeAttachments($request, $locked, $storedPaths);
                $locked->update(['status' => $completed ? 'execution_completed' : 'executing']);
                DB::table('procurement_execution_events')->insert([
                    'application_id' => $locked->id,
                    'actor_id' => $request->user()->id,
                    'event' => $completed ? 'completed' : 'draft_saved',
                    'details' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }

        return to_route('procurement.applications.show', $application->id);
    }

    public function assign(Request $request, ProcurementApplication $application): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['procurement_manager', 'system_admin']), 403);
        abort_unless(in_array($application->status, ['approved', 'executing', 'execution_completed'], true), 403);
        $data = $request->validate(['executor_id' => ['required', 'integer', 'exists:users,id']]);

        DB::transaction(function () use ($request, $application, $data) {
            $locked = ProcurementApplication::query()->lockForUpdate()->findOrFail($application->id);
            abort_unless(in_array($locked->status, ['approved', 'executing', 'execution_completed'], true), 403);
            $oldExecutor = $locked->executor_id;
            $locked->update(['executor_id' => $data['executor_id']]);
            DB::table('procurement_execution_events')->insert([
                'application_id' => $locked->id,
                'actor_id' => $request->user()->id,
                'event' => 'executor_assigned',
                'details' => "执行人由 {$oldExecutor} 调整为 {$data['executor_id']}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return to_route('procurement.applications.show', $application->id);
    }

    private function authorizeEditable(Request $request, ProcurementApplication $application, ProcurementAccess $access): void
    {
        abort_unless(in_array($application->status, ['approved', 'executing', 'execution_completed'], true), 403);
        abort_unless($access->canExecute($request->user(), $application), 403);
    }

    /** @param list<string> $storedPaths */
    private function storeAttachments(Request $request, ProcurementApplication $application, array &$storedPaths): void
    {
        $kinds = [
            'contract_document' => 'contract',
            'result_announcement_document' => 'result_announcement',
            'acceptance_document' => 'acceptance',
            'invoice_document' => 'invoice',
        ];

        foreach ($kinds as $input => $kind) {
            if ($request->hasFile($input)) {
                $this->storeAttachment($request->file($input), $kind, $request, $application, $storedPaths);
            }
        }

        foreach ($request->file('other_execution_documents', []) as $file) {
            $this->storeAttachment($file, 'other_execution', $request, $application, $storedPaths);
        }
    }

    /** @param list<string> $storedPaths */
    private function storeAttachment(UploadedFile $file, string $kind, Request $request, ProcurementApplication $application, array &$storedPaths): void
    {
        $path = $file->store('procurement/'.$application->id.'/execution', 'local');
        if ($path === false) {
            throw new \RuntimeException('采购执行附件存储失败');
        }
        $storedPaths[] = $path;

        ProcurementAttachment::create([
            'application_id' => $application->id,
            'uploaded_by' => $request->user()->id,
            'kind' => $kind,
            'original_name' => $file->getClientOriginalName(),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }
}
