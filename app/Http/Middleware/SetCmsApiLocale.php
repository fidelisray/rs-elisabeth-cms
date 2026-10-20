<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCmsApiLocale
{
    /**
     * Menangani bahasa untuk CMS API berdasarkan query string atau header Accept-Language,
     * dengan fallback ke bahasa default ('id').
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = config('app.supported_locales', ['id', 'en']);
        $fallbackLocale = config('app.fallback_locale', 'id');

        // 1. Prioritas utama: query parameter `lang` atau `locale`
        $requestedLocale = $request->query('lang') ?? $request->query('locale');

        // 2. Prioritas kedua: header Accept-Language bila query param tidak dikirim
        if (! $requestedLocale && $request->hasHeader('Accept-Language')) {
            $preferred = $request->getPreferredLanguage($supportedLocales);
            if ($preferred) {
                $requestedLocale = $preferred;
            }
        }

        // 3. Validasi terhadap bahasa yang didukung, jika tidak valid gunakan fallback
        if (! is_string($requestedLocale) || ! in_array(strtolower($requestedLocale), $supportedLocales, true)) {
            $requestedLocale = $fallbackLocale;
        } else {
            $requestedLocale = strtolower($requestedLocale);
        }

        app()->setLocale($requestedLocale);

        $response = $next($request);

        // Informasikan bahasa konten yang dikembalikan ke klien via header HTTP
        $response->headers->set('Content-Language', $requestedLocale);

        return $response;
    }
}
