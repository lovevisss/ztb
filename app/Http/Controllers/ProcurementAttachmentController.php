<?php

namespace App\Http\Controllers;

use App\Models\ProcurementAttachment;
use App\Services\ProcurementAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProcurementAttachmentController extends Controller
{
    public function download(Request $request, ProcurementAttachment $attachment, ProcurementAccess $access): StreamedResponse
    {
        abort_unless($access->canView($request->user(), $attachment->application), 403);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function preview(Request $request, ProcurementAttachment $attachment, ProcurementAccess $access): StreamedResponse
    {
        abort_unless($access->canView($request->user(), $attachment->application), 403);
        abort_unless(in_array($attachment->mime_type, ['application/pdf', 'image/png', 'image/jpeg'], true), 415);

        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }
}
