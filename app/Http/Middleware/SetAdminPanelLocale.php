<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menetapkan locale aktif khusus untuk Panel Admin Filament (termasuk request Livewire).
 *
 * - Halaman Login (/admin/login) dan seluruh request guest panel selalu dirender
 *   dalam Bahasa Inggris ('en') tanpa switcher bahasa.
 * - Pengguna terautentikasi memakai preferensi di Session dengan default Bahasa Indonesia ('id').
 * - APP_LOCALE dan APP_FALLBACK_LOCALE tetap 'id' sehingga endpoint /api/v1/cms/*
 *   yang dikelola SetCmsApiLocale tidak pernah terpengaruh.
 */
class SetAdminPanelLocale
{
    public const SESSION_KEY = 'locale';

    public const DEFAULT_LOCALE = 'id';

    public const LOGIN_LOCALE = 'en';

    /**
     * @var list<string>
     */
    public const SUPPORTED_LOCALES = ['id', 'en'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('filament.admin.auth.login') || ! $request->user()) {
            App::setLocale(self::LOGIN_LOCALE);
            Carbon::setLocale(self::LOGIN_LOCALE);

            return $next($request);
        }

        $sessionLocale = $request->hasSession()
            ? $request->session()->get(self::SESSION_KEY)
            : null;

        $resolved = (is_string($sessionLocale) && in_array($sessionLocale, self::SUPPORTED_LOCALES, true))
            ? $sessionLocale
            : self::DEFAULT_LOCALE;

        App::setLocale($resolved);
        Carbon::setLocale($resolved);

        return $next($request);
    }
}
