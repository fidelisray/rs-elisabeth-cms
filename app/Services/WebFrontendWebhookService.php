<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebFrontendWebhookService
{
    /**
     * Kirim sinyal pembatalan cache ke Web Frontend.
     *
     * Konfigurasi tidak lengkap adalah no-op (false), karena retry tidak akan
     * memperbaiki environment yang belum dikonfigurasi. Kegagalan HTTP maupun
     * Throwable jaringan dilempar ulang agar queue retry dapat bekerja.
     *
     * @throws \Throwable
     */
    public function dispatch(string $module, string $event = 'content.updated', ?string $slug = null): bool
    {
        $url = config('web_frontend.webhook_url');
        $consId = config('web_frontend.cons_id');
        $secretKey = config('web_frontend.secret_key');

        // cons_id wajib divalidasi sebelum dipakai sebagai argumen string. Tanpa
        // guard ini, null memicu TypeError sebelum masuk try/catch dan job gagal
        // tanpa jejak logging yang benar.
        if (empty($url) || empty($consId) || empty($secretKey)) {
            Log::warning('Web Frontend Webhook - URL, Consumer ID, atau Secret Key belum dikonfigurasi.', [
                'has_url' => ! empty($url),
                'has_cons_id' => ! empty($consId),
                'has_secret_key' => ! empty($secretKey),
            ]);

            return false;
        }

        try {
            $timestamp = (string) time();
            $payload = [
                'module' => $module,
                'event' => $event,
                'slug' => $slug,
            ];
            $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
            $signature = $this->generateSignature($timestamp, $consId, $rawBody, $secretKey);

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

            throw new \RuntimeException(
                "Webhook gagal dengan status HTTP {$response->status()} untuk modul '{$module}'."
            );
        } catch (\Throwable $e) {
            // Satu log error per kegagalan. Jangan log sebelum throw dan di catch
            // sekaligus, karena itu menggandakan noise setiap retry queue.
            Log::error('Web Frontend Webhook - Gagal mengirim webhook.', [
                'module' => $module,
                'event' => $event,
                'slug' => $slug,
                'exception' => get_class($e),
                'error' => $e->getMessage(),
            ]);

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
