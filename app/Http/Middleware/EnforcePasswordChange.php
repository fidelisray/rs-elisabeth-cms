<?php

namespace App\Http\Middleware;

use App\Filament\Pages\Auth\ForcePasswordChange;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blokir PENUH di server untuk user yang wajib mengganti password (K-8).
 *
 * Jika must_change_password = true, setiap request panel diarahkan ke halaman
 * ganti password. Satu-satunya yang diizinkan: halaman ganti password itu sendiri,
 * logout, dan request Livewire yang berasal dari komponen halaman tersebut.
 *
 * Middleware ini didaftarkan sebagai persistent agar juga berlaku pada request
 * Livewire (update komponen), bukan hanya pada pemuatan halaman. Tanpa itu,
 * pengguna bisa memanggil endpoint Livewire langsung dan melewati blokir.
 */
class EnforcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        if ($this->isAllowed($request)) {
            return $next($request);
        }

        // Selalu RedirectResponse. Livewire hanya menghentikan request update
        // (melempar abort) bila middleware persistent mengembalikan redirect;
        // respons jenis lain diabaikan dan komponen tetap diproses.
        return redirect()->to(route('filament.admin.pages.force-password-change'));
    }

    private function isAllowed(Request $request): bool
    {
        $route = $request->route()?->getName();

        if (in_array($route, [
            'filament.admin.pages.force-password-change',
            'filament.admin.auth.logout',
        ], true)) {
            return true;
        }

        // Request Livewire: hanya komponen halaman ganti password yang boleh.
        if ($request->header('X-Livewire') || $request->is('livewire*/update')) {
            return $this->livewireComponentsAreAllowed($request);
        }

        return false;
    }

    /**
     * Semua komponen pada payload Livewire harus milik halaman ganti password.
     * Satu saja komponen lain membuat seluruh request ditolak.
     */
    private function livewireComponentsAreAllowed(Request $request): bool
    {
        $components = $request->input('components');

        if (! is_array($components) || $components === []) {
            return false;
        }

        foreach ($components as $component) {
            $snapshot = json_decode((string) ($component['snapshot'] ?? ''), true);
            $name = $snapshot['memo']['name'] ?? null;

            if ($name !== ForcePasswordChange::class) {
                return false;
            }
        }

        return true;
    }
}
