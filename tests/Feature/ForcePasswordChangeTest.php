<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\ForcePasswordChange;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * MVP-1.3 (K-8): must_change_password adalah BLOKIR PENUH di server.
 * Popup/halaman di browser saja tidak cukup, jadi diuji dari sisi HTTP dan Livewire.
 */
class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        Role::findOrCreate('marketing', 'web');

        $user = User::factory()->create(array_merge([
            'must_change_password' => true,
            'password' => Hash::make('123456'),
        ], $attributes));
        $user->assignRole('marketing');

        return $user;
    }

    private function changePasswordUrl(): string
    {
        return route('filament.admin.pages.force-password-change');
    }

    public function test_user_who_must_change_password_is_redirected_from_dashboard(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect($this->changePasswordUrl());
    }

    public function test_user_is_redirected_from_every_resource_page(): void
    {
        $user = $this->user();

        foreach (['/admin/news', '/admin/articles', '/admin/promotions', '/admin/profile'] as $path) {
            $this->actingAs($user)
                ->get($path)
                ->assertRedirect($this->changePasswordUrl());
        }
    }

    public function test_change_password_page_itself_is_reachable(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get($this->changePasswordUrl())->assertOk();
    }

    public function test_logout_is_always_allowed(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)->post('/admin/logout');

        $this->assertNotSame($this->changePasswordUrl(), $response->headers->get('Location'));
        $this->assertGuest();
    }

    public function test_user_without_flag_is_not_redirected(): void
    {
        $user = $this->user(['must_change_password' => false]);

        $this->actingAs($user)->get('/admin')->assertOk();
    }

    /**
     * Ambil snapshot Livewire ASLI dari halaman yang dirender HTTP, lalu kirim
     * sebagai request update. Ini meniru penyerang yang memanggil endpoint
     * Livewire langsung, bukan Livewire::test() yang melewati pipeline HTTP.
     */
    private function livewireUpdate(User $user, string $pageUrl): TestResponse
    {
        // Snapshot diambil saat user belum diblokir, lalu user diwajibkan ganti password.
        $user->update(['must_change_password' => false]);
        $this->actingAs($user->fresh());

        $html = $this->get($pageUrl)->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
        $snapshot = htmlspecialchars_decode($matches[1], ENT_QUOTES);

        $user->update(['must_change_password' => true]);
        $this->actingAs($user->fresh());

        return $this->withHeaders(['X-Livewire' => 'true', 'Accept' => 'application/json'])
            ->postJson('/'.trim((string) parse_url(Livewire::getUpdateUri(), PHP_URL_PATH), '/'), [
                'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => []]],
            ]);
    }

    public function test_real_livewire_update_for_another_page_is_blocked(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'marketing@rs-elisabeth.com')->first();

        $response = $this->livewireUpdate($user, '/admin/news');

        // Update komponen halaman lain tidak boleh diproses: harus berupa redirect
        // ke halaman ganti password, bukan hasil render komponen.
        $this->assertNotSame(200, $response->getStatusCode(), 'Update Livewire halaman lain lolos blokir.');
        $this->assertStringNotContainsString('mountedActions', $response->getContent());
    }

    public function test_password_change_succeeds_and_clears_flag(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        Livewire::test(ForcePasswordChange::class)
            ->fillForm([
                'password' => 'PasswordBaru#2026',
                'passwordConfirmation' => 'PasswordBaru#2026',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(filament()->getUrl());

        $fresh = $user->fresh();
        $this->assertFalse($fresh->must_change_password);
        $this->assertTrue(Hash::check('PasswordBaru#2026', $fresh->password));
    }

    public function test_after_change_user_can_open_dashboard(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        Livewire::test(ForcePasswordChange::class)
            ->fillForm(['password' => 'PasswordBaru#2026', 'passwordConfirmation' => 'PasswordBaru#2026'])
            ->call('save');

        $this->actingAs($user->fresh())->get('/admin')->assertOk();
    }

    public function test_default_password_123456_is_rejected(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        Livewire::test(ForcePasswordChange::class)
            ->fillForm(['password' => '123456', 'passwordConfirmation' => '123456'])
            ->call('save')
            ->assertHasFormErrors(['password']);

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_admin_default_password_is_rejected(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        Livewire::test(ForcePasswordChange::class)
            ->fillForm(['password' => 'admin@123456', 'passwordConfirmation' => 'admin@123456'])
            ->call('save')
            ->assertHasFormErrors(['password']);
    }

    public function test_same_password_as_current_is_rejected(): void
    {
        $user = $this->user(['password' => Hash::make('SamaDenganLama#1')]);
        $this->actingAs($user);

        Livewire::test(ForcePasswordChange::class)
            ->fillForm(['password' => 'SamaDenganLama#1', 'passwordConfirmation' => 'SamaDenganLama#1'])
            ->call('save')
            ->assertHasFormErrors(['password']);

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_short_password_is_rejected(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        Livewire::test(ForcePasswordChange::class)
            ->fillForm(['password' => 'Abc#1', 'passwordConfirmation' => 'Abc#1'])
            ->call('save')
            ->assertHasFormErrors(['password']);
    }

    public function test_mismatched_confirmation_is_rejected(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        Livewire::test(ForcePasswordChange::class)
            ->fillForm(['password' => 'PasswordBaru#2026', 'passwordConfirmation' => 'Berbeda#2026x'])
            ->call('save')
            ->assertHasFormErrors(['passwordConfirmation']);

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_page_is_hidden_from_navigation(): void
    {
        $this->assertFalse(ForcePasswordChange::shouldRegisterNavigation());
    }

    public function test_voluntary_user_can_access_page_with_cancel_action(): void
    {
        $user = $this->user(['must_change_password' => false]);
        $this->actingAs($user);

        Livewire::test(ForcePasswordChange::class)
            ->assertSee('Ganti Password')
            ->assertDontSee('Anda harus mengganti password')
            ->assertSee('Batal / Kembali ke Profil')
            ->assertDontSeeHtml('id="force-logout-form"');
    }

    public function test_voluntary_user_changes_password_and_redirects_to_profile(): void
    {
        $user = $this->user(['must_change_password' => false]);
        $this->actingAs($user);

        Livewire::test(ForcePasswordChange::class)
            ->fillForm([
                'password' => 'Voluntary#2026Password',
                'passwordConfirmation' => 'Voluntary#2026Password',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(route('filament.admin.auth.profile'));

        $this->assertTrue(Hash::check('Voluntary#2026Password', $user->fresh()->password));
    }

    public function test_clear_validation_for_password_clears_both_password_and_confirmation_errors(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        Livewire::test(ForcePasswordChange::class)
            ->fillForm(['password' => '123456', 'passwordConfirmation' => 'Berbeda#2026x'])
            ->call('save')
            ->assertHasFormErrors(['password', 'passwordConfirmation'])
            ->call('clearValidationFor', 'password')
            ->assertHasNoFormErrors(['password', 'passwordConfirmation']);
    }

    public function test_clear_validation_for_confirmation_clears_only_confirmation_error(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        Livewire::test(ForcePasswordChange::class)
            ->fillForm(['password' => '123456', 'passwordConfirmation' => 'Berbeda#2026x'])
            ->call('save')
            ->assertHasFormErrors(['password', 'passwordConfirmation'])
            ->call('clearValidationFor', 'passwordConfirmation')
            ->assertHasNoFormErrors(['passwordConfirmation'])
            ->assertHasFormErrors(['password']);
    }
}
