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
    | CMS API Rate Limit
    |--------------------------------------------------------------------------
    | Batas request per menit untuk setiap klien (dihitung per X-Cons-ID).
    | Dipasang SETELAH verifikasi HMAC, sehingga request tanpa signature valid
    | ditolak 401 tanpa menghabiskan kuota klien yang sah.
    */
    'rate_limit_per_minute' => (int) env('CMS_API_RATE_LIMIT', 60),

    /*
    |--------------------------------------------------------------------------
    | CMS API Rate Limit per IP
    |--------------------------------------------------------------------------
    | Batas per IP untuk SEMUA request termasuk yang gagal HMAC. Lebih longgar
    | dari batas per klien karena beberapa klien bisa berbagi satu IP (NAT/proxy).
    */
    'rate_limit_per_ip_per_minute' => (int) env('CMS_API_RATE_LIMIT_PER_IP', 300),

    /*
    |--------------------------------------------------------------------------
    | CMS API Max Page
    |--------------------------------------------------------------------------
    | Batas atas parameter ?page. Nilai di atas batas dijepit, sehingga
    | parameter ini tidak bisa dipakai membuat key cache tanpa batas.
    */
    'max_page' => (int) env('CMS_API_MAX_PAGE', 1000),

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
