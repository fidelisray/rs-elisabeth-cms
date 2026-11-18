<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DynamicFormUpload;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadDynamicFormUploadController extends Controller
{
    public function __invoke(DynamicFormUpload $upload): StreamedResponse
    {
        $submission = $upload->submission()->withTrashed()->first();

        if ($submission === null) {
            abort(404, 'Berkas lampiran tidak ditemukan.');
        }

        Gate::authorize('downloadAttachment', $submission);

        if (! Storage::disk($upload->disk)->exists($upload->storage_path)) {
            abort(404, 'Berkas lampiran tidak ditemukan di penyimpanan.');
        }

        $safeName = Str::of($upload->original_name)
            ->replace(['/', '\\', '"', "\r", "\n"], '_')
            ->trim()
            ->toString();

        if ($safeName === '') {
            $safeName = 'lampiran-'.$upload->id;
        }

        return response()->streamDownload(function () use ($upload): void {
            $stream = Storage::disk($upload->disk)->readStream($upload->storage_path);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, $safeName, [
            'Content-Type' => $upload->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
        ]);
    }
}
