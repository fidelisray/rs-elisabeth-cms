<?php

namespace App\Providers\Filament;

use App\Enums\AdminNavigationGroup;
use App\Filament\Pages\Auth\CustomProfile;
use App\Filament\Widgets\DynamicFeedbackOverviewWidget;
use App\Filament\Widgets\FeedbackSectionWidget;
use App\Filament\Widgets\QuestionnaireSectionWidget;
use App\Http\Controllers\Admin\DownloadDynamicFormUploadController;
use App\Http\Controllers\Admin\SwitchPanelLocaleController;
use App\Http\Middleware\EnforcePasswordChange;
use App\Http\Middleware\SetAdminPanelLocale;
use App\Support\Theme\BrandColors;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use LaraZeus\SpatieTranslatable\SpatieTranslatablePlugin;

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
            ->authenticatedRoutes(function (): void {
                Route::post('/locale', SwitchPanelLocaleController::class)->name('locale.switch');
                Route::get('/dynamic-form-uploads/{upload}/download', DownloadDynamicFormUploadController::class)
                    ->name('dynamic-forms.uploads.download');
            })
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                static fn (): string => view('filament.components.locale-switcher')->render(),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                static fn (): HtmlString => new HtmlString(
                    '<p class="rse-login-help-note">Need help signing in? Contact your IT Administrator.</p>'
                ),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                static fn (): string => view('filament.theme.brand-tokens')->render(),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                static function (): string {
                    if (! request()->routeIs('filament.admin.resources.dynamic-forms.create', 'filament.admin.resources.dynamic-forms.edit')) {
                        return '';
                    }

                    return view('filament.resources.dynamic-forms.styles.wizard-builder-styles')->render();
                },
            )
            ->brandName('RS Elisabeth CMS')
            ->brandLogo(static fn (): View => view('filament.components.brand-logo'))
            ->brandLogoHeight('2.25rem')
            ->defaultThemeMode(ThemeMode::Light)
            ->colors(BrandColors::filamentColors())
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
                FeedbackSectionWidget::class,
                QuestionnaireSectionWidget::class,
                DynamicFeedbackOverviewWidget::class,
            ])
            ->navigationGroups(
                collect(AdminNavigationGroup::cases())
                    ->mapWithKeys(static fn (AdminNavigationGroup $group): array => [
                        $group->name => NavigationGroup::make()
                            ->label(static fn (): string => $group->getLabel())
                            ->collapsible(),
                    ])
                    ->all()
            )
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup(AdminNavigationGroup::AccessControl)
                    ->navigationLabel(static fn (): string => __('Peran'))
                    ->modelLabel(static fn (): string => __('model.role'))
                    ->pluralModelLabel(static fn (): string => __('Peran'))
                    ->navigationSort(2),
                SpatieTranslatablePlugin::make()
                    ->defaultLocales(config('app.supported_locales', ['id', 'en']))
                    ->getLocaleLabelUsing(static fn (string $locale): string => match ($locale) {
                        'id' => __('Bahasa Indonesia (ID)'),
                        'en' => __('Bahasa Inggris (EN)'),
                        default => strtoupper($locale),
                    }),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                SetAdminPanelLocale::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->persistentMiddleware([
                SetAdminPanelLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                // Blokir penuh di server untuk user yang wajib ganti password (K-8).
                // Persistent: juga berlaku pada request Livewire, bukan hanya muat halaman.
                EnforcePasswordChange::class,
            ], isPersistent: true);
    }
}
