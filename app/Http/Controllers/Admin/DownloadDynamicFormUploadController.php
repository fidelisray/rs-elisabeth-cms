<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DynamicFormUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DownloadDynamicFormUploadController extends Controller
{
    /**
     * Daftar tipe MIME yang aman ditampilkan secara inline di tab browser.
     * Tipe lain (teks, dokumen Office, arsip, dll.) selalu dipaksa sebagai attachment.
     *
     * @var list<string>
     */
    public const INLINE_PREVIEW_MIMES = [
        'image/webp',
        'image/jpeg',
        'image/png',
        'application/pdf',
    ];

    public static function supportsInlinePreview(?string $mimeType): bool
    {
        if (! is_string($mimeType) || trim($mimeType) === '') {
            return false;
        }

        return in_array(strtolower(trim($mimeType)), self::INLINE_PREVIEW_MIMES, true);
    }

    public function __invoke(Request $request, DynamicFormUpload $upload): StreamedResponse
    {
        $submission = $upload->submission()->withTrashed()->first();

        if ($submission === null) {
            abort(404, 'Berkas lampiran tidak ditemukan.');
        }

        Gate::authorize('downloadAttachment', $submission);

        $stream = null;
        if ($upload->storage_path !== '') {
            try {
                $stream = Storage::disk($upload->disk)->readStream($upload->storage_path);
            } catch (Throwable) {
                $stream = null;
            }
        }

        if (! is_resource($stream)) {
            abort(404, 'Berkas lampiran tidak ditemukan di penyimpanan.');
        }

        $safeName = Str::of((string) $upload->original_name)
            ->replace(['/', '\\', '"', '%', "\r", "\n", "\0"], '_')
            ->trim()
            ->toString();

        if ($safeName === '') {
            $safeName = 'lampiran-'.$upload->id;
        }

        $asciiFallback = (string) preg_replace(
            '/[^A-Za-z0-9._-]/',
            '_',
            Str::ascii($safeName)
        );
        $asciiFallback = trim($asciiFallback, '._ ');
        if ($asciiFallback === '') {
            $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
            $asciiFallback = 'lampiran-'.$upload->id.($ext !== '' ? '.'.$ext : '');
        }

        $isInline = $request->boolean('inline') && self::supportsInlinePreview($upload->mime_type);
        $dispositionType = $isInline
            ? HeaderUtils::DISPOSITION_INLINE
            : HeaderUtils::DISPOSITION_ATTACHMENT;

        $contentDisposition = HeaderUtils::makeDisposition(
            $dispositionType,
            $safeName,
            $asciiFallback
        );

        $headers = [
            'Content-Type' => $upload->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => $contentDisposition,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Referrer-Policy' => 'no-referrer',
        ];

        if ((int) $upload->size_bytes > 0) {
            $headers['Content-Length'] = (string) ((int) $upload->size_bytes);
        }

        if ($isInline) {
            $headers['Content-Security-Policy'] = "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; frame-ancestors 'self'";
        }

        return response()->stream(function () use ($stream): void {
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, $headers);
    }
}
