<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\CustomProfile;
use App\Filament\Widgets\FeedbackSectionWidget;
use App\Http\Middleware\SetAdminPanelLocale;
use App\Models\Article;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\SeedsTestRolesAndUsers;
use Tests\TestCase;
use Tests\Traits\GeneratesHmacHeaders;

class AdminLocaleSwitcherTest extends TestCase
{
    use GeneratesHmacHeaders;
    use RefreshDatabase;
    use SeedsTestRolesAndUsers;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.media_disk' => 'media-test']);
        Storage::fake('media-test');

        $this->seedDefaultTestUsersAndRoles();
        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])->assertExitCode(0);

        $this->superAdmin = User::where('email', 'admin@rs-elisabeth.com')->firstOrFail();
        $this->superAdmin->update(['must_change_password' => false]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_login_page_is_fixed_english_without_switcher_or_recovery_link_and_panel_defaults_to_indonesian(): void
    {
        $this->assertFalse(Schema::hasTable('super_admin_recovery_codes'));

        // Guest login page must be clean English, zero locale toggle, zero recovery link.
        $loginResponse = $this->get('/admin/login')->assertOk();
        $loginResponse
            ->assertSee('Sign in')
            ->assertSee('Email address')
            ->assertSee('Password')
            ->assertSee('Need help signing in? Contact your IT Administrator.')
            ->assertDontSee('data-rse-locale-switcher', false)
            ->assertDontSee('recovery-login', false)
            ->assertDontSee('Gunakan kode pemulihan');

        // Pure CLI-Only Break-Glass: web recovery route must return 404.
        $this->get('/admin/recovery-login')->assertNotFound();

        // Guest cannot call authenticated-only /admin/locale endpoint.
        $this->post('/admin/locale', ['locale' => 'en'])
            ->assertRedirect(route('filament.admin.auth.login'));
        $this->postJson('/admin/locale', ['locale' => 'en'])
            ->assertUnauthorized();

        // Authenticated user defaults to Indonesian (ID) with Topbar switcher.
        $this->actingAs($this->superAdmin);
        app()->forgetScopedInstances();

        $dashboardResponse = $this->get('/admin')->assertOk();
        $dashboardResponse
            ->assertSee('Dasbor')
            ->assertSee('Pemasaran &amp; Editorial', false)
            ->assertSee('Kontrol Akses')
            ->assertSee('Manajemen Pengguna')
            ->assertSee('Peran')
            ->assertSee('Kuesioner Kepuasan')
            ->assertSee('data-rse-locale-switcher="topbar"', false)
            ->assertSee('data-locale-option="id"', false)
            ->assertSee('data-locale-option="en"', false);

        // Super Admin CustomProfile shows CLI Break-Glass SOP in both locales.
        App::setLocale('id');
        Livewire::test(CustomProfile::class)
            ->assertSee('Pemulihan Akses Darurat (CLI Break-Glass)')
            ->assertSee('php artisan app:super-admin:reset-password');

        App::setLocale('en');
        Livewire::test(CustomProfile::class)
            ->assertSee('Emergency Access Recovery (CLI Break-Glass)')
            ->assertSee('php artisan app:super-admin:reset-password');

        App::setLocale('id');
    }

    public function test_switching_locale_to_english_and_back_persists_in_session_and_renders_translated_ui(): void
    {
        $this->actingAs($this->superAdmin);

        // Authenticated user switches to English while preserving internal /admin path & query string.
        $switchToEn = $this->from('/admin/articles?page=2')
            ->post('/admin/locale', ['locale' => 'en']);

        $switchToEn
            ->assertRedirect('/admin/articles?page=2')
            ->assertSessionHas(SetAdminPanelLocale::SESSION_KEY, 'en');

        app()->forgetScopedInstances();
        $this->withSession([SetAdminPanelLocale::SESSION_KEY => 'en'])
            ->get('/admin')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Marketing &amp; Editorial', false)
            ->assertSee('Access Control')
            ->assertSee('User Management')
            ->assertSee('Roles')
            ->assertSee('Satisfaction Questionnaires');

        // Switch back to Indonesian.
        $switchToId = $this->from('/admin')
            ->post('/admin/locale', ['locale' => 'id']);

        $switchToId
            ->assertRedirect('/admin')
            ->assertSessionHas(SetAdminPanelLocale::SESSION_KEY, 'id');

        app()->forgetScopedInstances();
        $this->withSession([SetAdminPanelLocale::SESSION_KEY => 'id'])
            ->get('/admin')
            ->assertOk()
            ->assertSee('Dasbor')
            ->assertSee('Pemasaran &amp; Editorial', false)
            ->assertSee('Kontrol Akses');
    }

    public function test_switch_locale_rejects_invalid_locale_and_blocks_open_redirect(): void
    {
        $this->actingAs($this->superAdmin);

        $this->postJson('/admin/locale', ['locale' => 'fr'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['locale']);

        $this->from('/admin')
            ->post('/admin/locale', ['locale' => 'jp'])
            ->assertSessionHasErrors(['locale']);

        // Cross-host, non-admin, and login Referers must fall back safely to /admin dashboard.
        $this->withHeaders(['Referer' => 'https://evil.example/admin/phish'])
            ->post('/admin/locale', ['locale' => 'en'])
            ->assertRedirect(route('filament.admin.pages.dashboard'));

        $this->withHeaders(['Referer' => url('/api/v1/cms/articles')])
            ->post('/admin/locale', ['locale' => 'en'])
            ->assertRedirect(route('filament.admin.pages.dashboard'));

        $this->withHeaders(['Referer' => route('filament.admin.auth.login')])
            ->post('/admin/locale', ['locale' => 'en'])
            ->assertRedirect(route('filament.admin.pages.dashboard'));
    }

    public function test_livewire_requests_register_and_respect_persistent_locale_middleware(): void
    {
        $this->actingAs($this->superAdmin);

        // Trigger Filament panel boot on an admin request so persistent middleware registers with Livewire.
        $this->withSession([SetAdminPanelLocale::SESSION_KEY => 'en'])
            ->get('/admin')
            ->assertOk();

        $this->assertContains(
            SetAdminPanelLocale::class,
            Livewire::getPersistentMiddleware(),
            'SetAdminPanelLocale must be registered as persistent middleware for Livewire AJAX requests.'
        );

        App::setLocale('en');
        Livewire::test(FeedbackSectionWidget::class)
            ->assertSee('Patient Feedback & Suggestions')
            ->assertSee('No feedback or suggestions submitted yet.');

        App::setLocale('id');
        Livewire::test(FeedbackSectionWidget::class)
            ->assertSee('Kritik & Saran Pasien')
            ->assertSee('Belum ada kritik dan saran masuk.');
    }

    public function test_panel_locale_switch_does_not_affect_app_config_or_cms_api_default_locale(): void
    {
        Article::create([
            'title' => ['id' => 'Artikel Isolasi Bahasa', 'en' => 'Language Isolation Article'],
            'slug' => ['id' => 'artikel-isolasi-bahasa', 'en' => 'language-isolation-article'],
            'shorts' => ['id' => 'Ringkasan ID', 'en' => 'EN Summary'],
            'content' => ['id' => 'Isi artikel ID.', 'en' => 'EN article content.'],
            'category' => ['id' => 'Kesehatan', 'en' => 'Health'],
            'is_active' => true,
        ]);

        $this->actingAs($this->superAdmin);

        $this->from('/admin')
            ->post('/admin/locale', ['locale' => 'en'])
            ->assertRedirect('/admin')
            ->assertSessionHas(SetAdminPanelLocale::SESSION_KEY, 'en');

        $this->assertSame('id', config('app.locale'));
        $this->assertSame('id', config('app.fallback_locale'));

        // Public CMS API without ?lang= or Accept-Language must continue defaulting to 'id'.
        $apiResponse = $this->withHmac()->getJson('/api/v1/cms/articles')->assertOk();
        $apiResponse->assertJsonPath('data.0.title', 'Artikel Isolasi Bahasa');
        $apiResponse->assertJsonPath('data.0.slug', 'artikel-isolasi-bahasa');
    }
}
