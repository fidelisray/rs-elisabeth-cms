<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\CustomProfile;
use App\Filament\Pages\Auth\RecoveryLogin;
use App\Filament\Widgets\FeedbackStatsWidget;
use App\Filament\Widgets\LatestFeedbackWidget;
use App\Http\Middleware\EnforcePasswordChange;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile(CustomProfile::class, isSimple: false) // Aktifkan halaman profil (ganti password) dengan CustomProfile
            ->routes(function (): void {
                Route::get('/recovery-login', RecoveryLogin::class)->name('auth.recovery-login');
            })
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): HtmlString => new HtmlString(
                    '<div class="mt-4 text-center text-sm text-gray-500 dark:text-gray-400">'
                    .'<a href="'.e(route('filament.admin.auth.recovery-login')).'" class="font-medium text-primary-600 hover:underline dark:text-primary-400">'
                    .'Gunakan kode pemulihan Super Admin'
                    .'</a></div>'
                ),
            )
            ->colors([
                'primary' => Color::Amber,
            ])
            // Icon web (favicon) pada tab browser untuk seluruh halaman panel admin.
            ->favicon(asset('images/web-icon.webp'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
                FeedbackStatsWidget::class,
                LatestFeedbackWidget::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                // Blokir penuh di server untuk user yang wajib ganti password (K-8).
                // Persistent: juga berlaku pada request Livewire, bukan hanya muat halaman.
                EnforcePasswordChange::class,
            ], isPersistent: true);
    }
}
