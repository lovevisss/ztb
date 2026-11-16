<?php

namespace App\Http\Controllers;

use App\Services\LegacyProcurementAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LegacyAttachmentController extends Controller
{
    public function download(Request $request, string $sourceId, int $attachment, LegacyProcurementAccess $access): StreamedResponse
    {
        $project = DB::table('legacy_procurement_records')
            ->where('source_table', 'zcy_cgsq')->where('source_id', $sourceId)->first();
        abort_if($project === null, 404);
        abort_unless($access->canView($request->user(), $project), 403);

        $file = DB::table('legacy_procurement_attachments')
            ->where('id', $attachment)->where('application_source_id', $sourceId)->first();
        abort_if($file === null || $file->migration_status !== 'available' || $file->stored_path === null, 404);

        $path = $file->stored_path;
        abort_unless(preg_match('~\Alegacy/\d{4}/\d{2}/\d{2}/[A-Za-z0-9][A-Za-z0-9._-]*\z~D', $path) === 1, 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path) && ! is_link($disk->path($path)), 404);

        $name = trim((string) $file->name) ?: basename($path);
        if (pathinfo($name, PATHINFO_EXTENSION) === '') {
            $name .= '.'.pathinfo($path, PATHINFO_EXTENSION);
        }

        return $disk->download($path, $name, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
