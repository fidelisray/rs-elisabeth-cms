<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CMS API Tolerance
    |--------------------------------------------------------------------------
    | Toleransi perbedaan waktu (dalam menit) antara timestamp client
    | dan timestamp server. Mencegah Replay Attack.
    */
    'tolerance_minutes' => (int) env('CMS_API_TOLERANCE_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | CMS API Clients
    |--------------------------------------------------------------------------
    | Daftar klien yang diizinkan mengakses API CMS.
    | Struktur: 'Cons-ID' => 'Secret-Key'
    |
    | Jika ada klien baru (misal: Aplikasi Mobile), cukup tambahkan
    | baris baru di sini dan di file .env.
    */
    'clients' => [
        env('CMS_API_CONSID_WEB') => env('CMS_API_SECRET_WEB'),

        // Contoh untuk klien masa depan:
        // env('CMS_API_CONSID_MOBILE') => env('CMS_API_SECRET_MOBILE'),
    ],
];
