<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Web Frontend Webhook Cache Invalidation Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi webhook untuk memicu invalidasi cache (Zero-Delay Cache Invalidation)
    | pada modul Web Frontend ('rs-elisabeth-web') secara instan via HMAC SHA-256.
    |
    */

    'webhook_url' => env('WEB_FRONTEND_WEBHOOK_URL'),

    'cons_id' => env('WEB_FRONTEND_WEBHOOK_CONSID', env('CMS_API_CONSID_WEB', 'WEB-FRONTEND')),

    'secret_key' => env('WEB_FRONTEND_WEBHOOK_SECRET', env('CMS_API_SECRET_WEB', '')),

    'timeout' => (int) env('WEB_FRONTEND_WEBHOOK_TIMEOUT', 5),
];
