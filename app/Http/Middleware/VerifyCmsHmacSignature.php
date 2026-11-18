<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class VerifyCmsHmacSignature
{
    /**
     * Memvalidasi request API CMS menggunakan algoritma HMAC-SHA256
     * berdasarkan standar industri (mirip mekanisme BPJS / Medin).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $consId = $request->header('X-Cons-ID');
        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');

        // 1. Cek keberadaan semua header wajib
        if (empty($consId) || empty($timestamp) || empty($signature)) {
            return $this->unauthorizedResponse('Missing required headers: X-Cons-ID, X-Timestamp, X-Signature.');
        }

        // 2. Cek apakah Cons-ID terdaftar di config
        $clients = config('cms_api.clients', []);
        if (! array_key_exists($consId, $clients) || empty($clients[$consId])) {
            return $this->unauthorizedResponse('Unknown Client ID.');
        }

        $secretKey = $clients[$consId];

        // 3. Validasi Timestamp Drift (Mencegah Replay Attack)
        $toleranceMinutes = config('cms_api.tolerance_minutes', 5);
        $serverTime = time();
        $clientTime = (int) $timestamp;

        $diffMinutes = abs($serverTime - $clientTime) / 60;

        if ($diffMinutes > $toleranceMinutes) {
            return $this->unauthorizedResponse('Timestamp expired or drifted beyond tolerance.');
        }

        // 4. Hitung dan verifikasi Signature
        // GET/HEAD (tanpa body): base64(hmac_sha256({timestamp}{consId}, secret)).
        // POST multipart upload (/forms/{slug}/uploads): base64(hmac_sha256({timestamp}{consId}{X-Content-SHA256}, secret)),
        //   disertai verifikasi hash_file('sha256', ...) pada berkas yang diterima.
        // POST/PUT/PATCH/DELETE lainnya: base64(hmac_sha256({timestamp}{consId}{rawBody}, secret)).
        if ($this->isDynamicFormMultipartUpload($request)) {
            $contentSha256 = (string) $request->header('X-Content-SHA256', '');

            if (preg_match('/^[a-f0-9]{64}$/i', $contentSha256) !== 1) {
                return $this->unauthorizedResponse('Missing or invalid X-Content-SHA256 header.');
            }

            $files = Arr::flatten($request->allFiles());
            if (count($files) > 1) {
                return $this->unauthorizedResponse('Only a single file per upload request is allowed.');
            }

            if (count($files) === 1) {
                $uploadedFile = $files[0];
                $realPath = $uploadedFile instanceof UploadedFile && $uploadedFile->isValid()
                    ? $uploadedFile->getRealPath()
                    : false;
                $actualSha256 = is_string($realPath) && $realPath !== '' ? hash_file('sha256', $realPath) : false;

                if ($actualSha256 === false || ! hash_equals(strtolower($contentSha256), strtolower($actualSha256))) {
                    return $this->unauthorizedResponse('Uploaded file content SHA-256 mismatch.');
                }
            } elseif (! hash_equals(strtolower($contentSha256), hash('sha256', ''))) {
                return $this->unauthorizedResponse('Empty upload SHA-256 mismatch.');
            }

            $signedBody = $contentSha256;
        } else {
            $signedBody = in_array($request->method(), ['GET', 'HEAD'], true) ? '' : $request->getContent();
        }

        $expectedSignatureRaw = hash_hmac('sha256', $timestamp.$consId.$signedBody, $secretKey, true);
        $expectedSignature = base64_encode($expectedSignatureRaw);

        // Gunakan hash_equals untuk mencegah Timing Attack
        if (! hash_equals($expectedSignature, $signature)) {
            return $this->unauthorizedResponse('Invalid signature.');
        }

        // 5. Batas laju per klien. Sengaja dihitung DI SINI, setelah signature terbukti valid,
        //    bukan lewat middleware throttle terpisah: Laravel mengurutkan ulang middleware
        //    throttle ke depan, sehingga request yang gagal HMAC ikut menghabiskan kuota.
        //    Cons-ID bukan rahasia; tanpa ini penyerang yang memalsukan signature dapat
        //    mengunci klien sah dengan 429.
        $limiterKey = 'cms-api:'.$consId;
        $maxAttempts = (int) config('cms_api.rate_limit_per_minute', 60);

        if (RateLimiter::tooManyAttempts($limiterKey, $maxAttempts)) {
            return response()->json([
                'success' => false,
                'status_code' => 429,
                'message' => 'Too many requests.',
                'errors' => null,
            ], Response::HTTP_TOO_MANY_REQUESTS, [
                'Retry-After' => RateLimiter::availableIn($limiterKey),
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        RateLimiter::hit($limiterKey, 60);

        return $next($request);
    }

    private function isDynamicFormMultipartUpload(Request $request): bool
    {
        if (! $request->isMethod('POST') || ! $request->is('api/v1/cms/forms/*/uploads')) {
            return false;
        }

        $contentType = strtolower((string) $request->header('Content-Type', ''));

        return str_starts_with($contentType, 'multipart/form-data')
            || $request->allFiles() !== []
            || $request->hasHeader('X-Content-SHA256');
    }

    /**
     * Kembalikan respon 401 baku
     */
    private function unauthorizedResponse(string $message): Response
    {
        return response()->json([
            'success' => false,
            'status_code' => 401,
            'message' => 'Unauthorized access.',
            'errors' => $message,
        ], Response::HTTP_UNAUTHORIZED);
    }
}
