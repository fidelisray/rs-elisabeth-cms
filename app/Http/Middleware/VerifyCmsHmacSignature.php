<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        // Format: base64_encode(hash_hmac('sha256', {timestamp} . {consId}, {secretKey}, true))
        $expectedSignatureRaw = hash_hmac('sha256', $timestamp.$consId, $secretKey, true);
        $expectedSignature = base64_encode($expectedSignatureRaw);

        // Gunakan hash_equals untuk mencegah Timing Attack
        if (! hash_equals($expectedSignature, $signature)) {
            return $this->unauthorizedResponse('Invalid signature.');
        }

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
