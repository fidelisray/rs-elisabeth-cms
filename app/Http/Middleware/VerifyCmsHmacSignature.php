<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        // GET/HEAD (tanpa body): base64(hmac_sha256({timestamp}.{consId}, secret)), format lama.
        // POST/PUT/PATCH/DELETE: base64(hmac_sha256({timestamp}.{consId}.{rawBody}, secret)).
        // Body ikut ditandatangani agar payload tidak bisa diubah atau di-replay dengan isi lain.
        // Body GET diabaikan: klien GET tidak mengirim body, dan test lama mengirim "[]".
        $signedBody = in_array($request->method(), ['GET', 'HEAD'], true) ? '' : $request->getContent();
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
