<?php

return [
    'enabled' => (bool) env('RS_API_ENABLED', true),
    'base_url' => env('RS_API_BASE_URL'),
    'api_key' => env('RS_API_KEY'),
    'timeout' => (int) env('RS_API_TIMEOUT', 10),
    // Pengecualian sementara yang dikonfirmasi administrator. Nilai kosong mengaktifkan semua kode kembali.
    'blacklist_specialties' => array_values(array_unique(array_filter(
        array_map('trim', explode(',', (string) env('RS_API_BLACKLIST_SPECIALTIES', 'KSM 37'))),
        fn (string $code): bool => $code !== ''
    ))),
    // Pertahankan jeda existing 1 detik; dapat disesuaikan tanpa mengubah source.
    'fetch_delay_microseconds' => max(0, (int) env('RS_API_FETCH_DELAY_MICROSECONDS', 1000000)),
    'medin_endpoint' => env('RS_MEDIN_ENDPOINT'),
    'medin_consid' => env('RS_MEDIN_API_CONSID'),
    'medin_secretkey' => env('RS_MEDIN_API_SECRETKEY'),

    // Kredensial untuk generate token
    'auth' => [
        'endpoint' => env('RS_API_AUTH_ENDPOINT'),
        'username' => env('RS_API_USERNAME'),
        'password' => env('RS_API_PASSWORD'),
    ],

    'token_ttl_buffer' => (int) env('RS_API_TOKEN_BUFFER', 60),

    'cache_ttl' => [
        // External RS API (dalam detik)
        'dokter' => 3600,
        'units' => 3600,
        'specialty' => 3600,
        'dokter_by_speciality' => 3600,
        'staff' => 3600,
        'jadwal' => 1800,
        'klinik' => 1800,
        'promotions' => 1800,
        'glosarium' => 86400,
        'articles' => 1800,
        'elisanews' => 1800,
    ],
];
