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
}
