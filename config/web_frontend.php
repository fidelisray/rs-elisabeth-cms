<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Web Frontend Webhook Cache Invalidation & Public URL Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi webhook untuk memicu invalidasi cache (Zero-Delay Cache Invalidation)
    | pada modul Web Frontend ('rs-elisabeth-web') secara instan via HMAC SHA-256,
    | serta URL dasar publik Web Frontend untuk tautan berbagi dan QR Code formulir.
    |
    */

    'public_url' => env('WEB_FRONTEND_PUBLIC_URL'),

    'webhook_url' => env('WEB_FRONTEND_WEBHOOK_URL'),

    // Kredensial webhook WAJIB disediakan terpisah dari kredensial outbound CMS_API_*.
    // Identitas di sini adalah identitas CMS sebagai pengirim (arah CMS -> Web).
    'cons_id' => env('WEB_FRONTEND_WEBHOOK_CONSID'),

    'secret_key' => env('WEB_FRONTEND_WEBHOOK_SECRET'),

    'timeout' => (int) env('WEB_FRONTEND_WEBHOOK_TIMEOUT', 5),
];
