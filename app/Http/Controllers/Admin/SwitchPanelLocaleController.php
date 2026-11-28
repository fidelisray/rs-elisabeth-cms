<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetAdminPanelLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SwitchPanelLocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(SetAdminPanelLocale::SUPPORTED_LOCALES)],
        ]);

        $request->session()->put(SetAdminPanelLocale::SESSION_KEY, $validated['locale']);

        return redirect()->to($this->resolveSafeRedirectTarget($request));
    }

    /**
     * Mencegah open-redirect dan redirect loop saat kembali dari endpoint switcher.
     * Hanya mengizinkan redirect ke path internal terautentikasi /admin pada host yang sama.
     */
    private function resolveSafeRedirectTarget(Request $request): string
    {
        $fallback = route('filament.admin.pages.dashboard');

        $previous = url()->previous();
        $current = $request->fullUrl();

        if ($previous === '' || $previous === $current) {
            return $fallback;
        }

        $previousParts = parse_url($previous);
        $appParts = parse_url(url('/'));

        if (! is_array($previousParts) || ! is_array($appParts)) {
            return $fallback;
        }

        $previousHost = strtolower((string) ($previousParts['host'] ?? ''));
        $appHost = strtolower((string) ($appParts['host'] ?? ''));
        $previousPath = (string) ($previousParts['path'] ?? '');
        $switchPath = (string) (parse_url(route('filament.admin.locale.switch'), PHP_URL_PATH) ?? '/admin/locale');
        $loginPath = (string) (parse_url(route('filament.admin.auth.login'), PHP_URL_PATH) ?? '/admin/login');

        if ($previousHost === '' || $previousHost !== $appHost) {
            return $fallback;
        }

        if (
            $previousPath === $switchPath
            || $previousPath === $loginPath
            || ($previousPath !== '/admin' && ! str_starts_with($previousPath, '/admin/'))
        ) {
            return $fallback;
        }

        $target = $previousPath;
        if (isset($previousParts['query']) && $previousParts['query'] !== '') {
            $target .= '?'.$previousParts['query'];
        }

        return $target;
    }
}
