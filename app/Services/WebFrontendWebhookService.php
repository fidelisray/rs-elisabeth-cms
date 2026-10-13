<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebFrontendWebhookService
{
    /**
     * Kirim sinyal pembatalan cache ke Web Frontend.
     *
     * @param  string  $module  Nama modul (news, articles, promotions, facilities, room_facilities, banner_promotions, all)
     * @param  string  $event  Tipe event (content.updated, content.created, content.deleted)
     * @param  string|null  $slug  Slug entitas yang diubah/dihapus (opsional)
     * @return bool True jika berhasil direspon 200 OK oleh Web Frontend.
     *              Melempar RuntimeException bila konfigurasi tidak lengkap atau
     *              request gagal, agar mekanisme retry queue dapat bekerja.
     *
     * @throws \RuntimeException
     */
    public function dispatch(string $module, string $event = 'content.updated', ?string $slug = null): bool
    {
        $url = config('web_frontend.webhook_url');
        $consId = config('web_frontend.cons_id');
        $secretKey = config('web_frontend.secret_key');

        if (empty($url) || empty($secretKey)) {
            Log::warning('Web Frontend Webhook - URL atau Secret Key belum dikonfigurasi.');

            // Konfigurasi tidak lengkap = no-op yang aman. Tidak dilempar karena
            // retry tidak akan memperbaikinya, dan kondisi ini wajar terjadi pada
            // environment non-production / test yang belum mengisi kredensial webhook.
            return false;
        }

        $timestamp = (string) time();
        // Catatan: timestamp dikirim HANYA lewat header X-Timestamp (bukan di body),
        // untuk menghindari duplikasi sumber nilai yang berpotensi tidak konsisten.
        $payload = [
            'module' => $module,
            'event' => $event,
            'slug' => $slug,
        ];

        $rawBody = json_encode($payload);
        $signature = $this->generateSignature($timestamp, $consId, $rawBody, $secretKey);

        try {
            $response = Http::withHeaders([
                'X-Cons-ID' => $consId,
                'X-Timestamp' => $timestamp,
                'X-Signature' => $signature,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout(config('web_frontend.timeout', 5))
                ->withBody($rawBody, 'application/json')
                ->post($url);

            if ($response->successful()) {
                Log::info("Web Frontend Webhook - Sukses membatalkan cache modul '{$module}'.", [
                    'module' => $module,
                    'event' => $event,
                    'slug' => $slug,
                    'status' => $response->status(),
                ]);

                return true;
            }

            Log::error("Web Frontend Webhook - Gagal dengan status {$response->status()}", [
                'module' => $module,
                'event' => $event,
                'slug' => $slug,
                'body' => $response->body(),
            ]);

            // Lempar exception agar mekanisme retry queue (tries/backoff) terpicu
            // untuk kegagalan HTTP non-2xx, bukan hanya kegagalan jaringan.
            throw new \RuntimeException("Webhook gagal dengan status HTTP {$response->status()} untuk modul '{$module}'.");
        } catch (\Exception $e) {
            Log::error('Web Frontend Webhook - Exception saat mengirim webhook: '.$e->getMessage(), [
                'module' => $module,
                'event' => $event,
                'slug' => $slug,
            ]);

            // Lempar ulang agar job mencatat kegagalan & menjalankan retry.
            throw $e;
        }
    }

    /**
     * Hitung HMAC SHA-256 signature sesuai kontrak API Web Frontend.
     */
    public function generateSignature(string $timestamp, string $consId, string $rawBody, string $secretKey): string
    {
        $message = $timestamp.$consId.$rawBody;

        return base64_encode(hash_hmac('sha256', $message, $secretKey, true));
    }
}
