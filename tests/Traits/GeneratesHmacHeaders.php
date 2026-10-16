<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\Config;

trait GeneratesHmacHeaders
{
    /**
     * Helper untuk menyuntikkan header HMAC yang valid
     * ke dalam request HTTP untuk keperluan pengujian API CMS.
     *
     * @return $this
     */
    protected function withHmac()
    {
        $consId = 'TESTING-APP';
        $secretKey = 'TESTING-SECRET-12345';

        // Memastikan konfigurasi tersedia saat runtime testing berjalan
        Config::set("cms_api.clients.{$consId}", $secretKey);
        Config::set('cms_api.tolerance_minutes', 5);

        $timestamp = (string) time();

        $signatureRaw = hash_hmac('sha256', $timestamp.$consId, $secretKey, true);
        $signature = base64_encode($signatureRaw);

        return $this->withHeaders([
            'X-Cons-ID' => $consId,
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
            'Accept' => 'application/json',
        ]);
    }
}
