<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batas laju per IP untuk API CMS yang SELALU berjalan sebelum verifikasi HMAC.
 *
 * Mengapa kelas tersendiri: Laravel mengurutkan ulang middleware berdasarkan daftar
 * prioritas, dan semua "throttle:..." berbagi kelas ThrottleRequests yang sama sehingga
 * tidak dapat ditempatkan sebelum HMAC. Subkelas ini bukan anggota daftar prioritas,
 * jadi posisinya mengikuti urutan yang ditulis pada route.
 *
 * Tujuannya: request yang akan gagal HMAC (banjir, tebakan signature) tetap dibatasi.
 * Bila batas ini berada setelah HMAC, request yang ditolak 401 tidak pernah dihitung.
 */
class ThrottleCmsApiByIp extends ThrottleRequests
{
    public function handle($request, Closure $next, $maxAttempts = 1, $decayMinutes = 1, $prefix = ''): Response
    {
        return parent::handle($request, $next, 'cms-api-ip');
    }
}
