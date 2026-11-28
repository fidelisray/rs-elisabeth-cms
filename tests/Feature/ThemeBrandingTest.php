<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Theme\BrandColors;
use Filament\Enums\ThemeMode;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsTestRolesAndUsers;
use Tests\TestCase;

class ThemeBrandingTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTestRolesAndUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDefaultTestUsersAndRoles();
    }

    public function test_admin_panel_registers_calibrated_rs_elisabeth_brand_palette_and_branding(): void
    {
        $panel = Filament::getPanel('admin');
        $colors = $panel->getColors();

        $this->assertSame(BrandColors::primary(), $colors['primary']);
        $this->assertSame('#095988', $colors['primary'][600]);
        $this->assertSame('#1997e6', $colors['primary'][400]);

        $this->assertSame(BrandColors::secondary(), $colors['success']);
        $this->assertSame('#159e74', $colors['success'][500]);
        $this->assertSame('#0f7a59', $colors['success'][600]);

        $this->assertSame(Color::Slate, $colors['gray']);
        $this->assertSame(Color::Sky, $colors['info']);
        $this->assertSame(Color::Amber, $colors['warning']);
        $this->assertSame(Color::Rose, $colors['danger']);

        $this->assertSame('RS Elisabeth CMS', $panel->getBrandName());
        $this->assertSame(ThemeMode::Light, $panel->getDefaultThemeMode());
        $this->assertNotContains(FilamentInfoWidget::class, $panel->getWidgets());
    }

    public function test_brand_palette_pairs_meet_wcag_aa_text_contrast(): void
    {
        $primary = BrandColors::primary();
        $secondary = BrandColors::secondary();

        // Light primary button & hover vs white text
        $this->assertTrue(Color::isTextContrastRatioAccessible($primary[600], '#ffffff'));
        $this->assertTrue(Color::isTextContrastRatioAccessible($primary[500], '#ffffff'));

        // Dark primary button & hover vs dark Slate text
        $this->assertTrue(Color::isTextContrastRatioAccessible($primary[400], '#0f172a'));
        $this->assertTrue(Color::isTextContrastRatioAccessible($primary[300], '#0f172a'));

        // Primary badges (light & dark)
        $this->assertTrue(Color::isTextContrastRatioAccessible($primary[700], $primary[100]));
        $this->assertTrue(Color::isTextContrastRatioAccessible($primary[200], $primary[900]));

        // Secondary / Fresh Emerald button & badges (light & dark)
        $this->assertTrue(Color::isTextContrastRatioAccessible($secondary[600], '#ffffff'));
        $this->assertTrue(Color::isTextContrastRatioAccessible($secondary[500], '#0f172a'));
        $this->assertTrue(Color::isTextContrastRatioAccessible($secondary[800], $secondary[100]));
        $this->assertTrue(Color::isTextContrastRatioAccessible($secondary[200], $secondary[950]));
    }

    public function test_login_and_dashboard_render_brand_tokens_and_logo(): void
    {
        $loginResponse = $this->get('/admin/login')->assertOk();
        $loginResponse->assertSee('data-rse-brand-tokens', false);
        $loginResponse->assertSee('#095988', false);
        $loginResponse->assertSee('web-icon.webp', false);
        $loginResponse->assertSee('RS Elisabeth');
        $loginResponse->assertSee('rse-login-help-note', false);
        $loginResponse->assertDontSee('data-rse-locale-switcher', false);

        $this->get('/admin/recovery-login')->assertNotFound();

        $user = User::where('email', 'marketing@rs-elisabeth.com')->firstOrFail();
        $user->update(['must_change_password' => false]);

        $dashboardResponse = $this->actingAs($user)->get('/admin')->assertOk();
        $dashboardResponse->assertSee('data-rse-brand-tokens', false);
        $dashboardResponse->assertSee('rse-brand-lockup', false);
        $dashboardResponse->assertSee('web-icon.webp', false);
        $dashboardResponse->assertDontSee('fi-wi-filament-info', false);
    }

    public function test_custom_views_use_brand_tokens_instead_of_hardcoded_amber_primary_accents(): void
    {
        $viewsWithoutAmberAccent = [
            resource_path('views/filament/widgets/dynamic-feedback-overview-widget.blade.php'),
            resource_path('views/filament/resources/dynamic-forms/pages/analytics.blade.php'),
            resource_path('views/filament/resources/dynamic-forms/components/live-preview.blade.php'),
            resource_path('views/filament/resources/dynamic-forms/components/share-modal.blade.php'),
            resource_path('views/filament/resources/dynamic-form-submissions/pages/view-submission.blade.php'),
            resource_path('views/filament/resources/dynamic-forms/styles/wizard-builder-styles.blade.php'),
        ];

        foreach ($viewsWithoutAmberAccent as $path) {
            $contents = (string) file_get_contents($path);

            $this->assertStringNotContainsString('#b45309', $contents, "Found legacy amber hex #b45309 in {$path}");
            $this->assertStringNotContainsString('#fbbf24', $contents, "Found legacy amber hex #fbbf24 in {$path}");
            $this->assertStringNotContainsString('#d97706', $contents, "Found legacy amber hex #d97706 in {$path}");
        }
    }
}
