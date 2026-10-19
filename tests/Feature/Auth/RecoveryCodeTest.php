<?php

namespace Tests\Feature\Auth;

use App\Console\Commands\RegenerateSuperAdminRecoveryCodesCommand;
use App\Filament\Pages\Auth\CustomProfile;
use App\Filament\Pages\Auth\RecoveryLogin;
use App\Models\AuditLog;
use App\Models\SuperAdminRecoveryCode;
use App\Models\User;
use App\Services\RecoveryCodeService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * MVP-1.5 (K-5, K-24, K-26): pengujian end-to-end recovery code super_admin.
 */
class RecoveryCodeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: list<string>}
     */
    private function initSuperAdminWithCodes(): array
    {
        $this->seed(DatabaseSeeder::class);

        Artisan::call('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com']);
        $output = Artisan::output();

        preg_match_all('/RECOVERY_CODE_\d+=([23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{4}(?:-[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{4}){3})/', $output, $matches);
        /** @var list<string> $codes */
        $codes = $matches[1] ?? [];

        $admin = User::where('email', 'admin@rs-elisabeth.com')->firstOrFail();
        $admin->update(['must_change_password' => false]);

        return [$admin->fresh(), $codes];
    }

    public function test_init_super_admin_prints_and_stores_ten_hashed_recovery_codes(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        Artisan::call('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com']);
        $output = Artisan::output();

        preg_match_all('/RECOVERY_CODE_\d+=([23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{4}(?:-[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{4}){3})/', $output, $matches);
        $codes = $matches[1] ?? [];

        $this->assertCount(10, $codes);

        $admin = User::where('email', 'admin@rs-elisabeth.com')->firstOrFail();
        $stored = SuperAdminRecoveryCode::where('user_id', $admin->getKey())->get();

        $this->assertCount(10, $stored);

        foreach ($codes as $code) {
            $this->assertFalse(
                $stored->contains(fn (SuperAdminRecoveryCode $row) => $row->code_hash === $code),
                'Plaintext recovery code tidak boleh disimpan di database.'
            );
        }
    }

    public function test_recovery_login_page_is_accessible_to_guest_and_linked_from_login_page(): void
    {
        $this->get('/admin/recovery-login')
            ->assertOk()
            ->assertSee('Pemulihan Akses Super Admin');

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee(route('filament.admin.auth.recovery-login'));
    }

    public function test_valid_recovery_code_logs_in_super_admin_sets_must_change_password_and_redirects(): void
    {
        [$admin, $codes] = $this->initSuperAdminWithCodes();

        Livewire::test(RecoveryLogin::class)
            ->fillForm([
                'email' => $admin->email,
                'recovery_code' => $codes[0],
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect(route('filament.admin.pages.force-password-change'));

        $this->assertTrue(Auth::check());
        $this->assertSame($admin->getKey(), Auth::id());
        $this->assertTrue($admin->fresh()->must_change_password);
        $this->assertSame(9, app(RecoveryCodeService::class)->remainingCount($admin));
    }

    public function test_same_recovery_code_cannot_be_used_twice(): void
    {
        [$admin, $codes] = $this->initSuperAdminWithCodes();

        Livewire::test(RecoveryLogin::class)
            ->fillForm([
                'email' => $admin->email,
                'recovery_code' => $codes[0],
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        Auth::logout();

        Livewire::test(RecoveryLogin::class)
            ->fillForm([
                'email' => $admin->email,
                'recovery_code' => $codes[0],
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertFalse(Auth::check());
        $this->assertSame(9, app(RecoveryCodeService::class)->remainingCount($admin));
    }

    public function test_wrong_code_wrong_email_and_non_super_admin_return_identical_error_message(): void
    {
        [$admin, $codes] = $this->initSuperAdminWithCodes();
        $marketing = User::where('email', 'marketing@rs-elisabeth.com')->firstOrFail();

        $wrongCodeComponent = Livewire::test(RecoveryLogin::class)
            ->fillForm([
                'email' => $admin->email,
                'recovery_code' => '2345-6789-ABCD-EFGH',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $wrongEmailComponent = Livewire::test(RecoveryLogin::class)
            ->fillForm([
                'email' => 'tidak.ada@rs-elisabeth.com',
                'recovery_code' => $codes[0],
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $marketingComponent = Livewire::test(RecoveryLogin::class)
            ->fillForm([
                'email' => $marketing->email,
                'recovery_code' => $codes[0],
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $msg1 = $wrongCodeComponent->instance()->getErrorBag()->first('data.email');
        $msg2 = $wrongEmailComponent->instance()->getErrorBag()->first('data.email');
        $msg3 = $marketingComponent->instance()->getErrorBag()->first('data.email');

        $this->assertSame(RecoveryLogin::INVALID_CREDENTIALS_MESSAGE, $msg1);
        $this->assertSame($msg1, $msg2);
        $this->assertSame($msg1, $msg3);
    }

    public function test_rate_limits_sixth_attempt_within_fifteen_minutes(): void
    {
        [$admin, $codes] = $this->initSuperAdminWithCodes();
        RateLimiter::clear('recovery-login:'.$admin->email.'|127.0.0.1');

        for ($i = 1; $i <= 5; $i++) {
            Livewire::test(RecoveryLogin::class)
                ->fillForm([
                    'email' => $admin->email,
                    'recovery_code' => '2345-6789-ABCD-EFGH',
                ])
                ->call('authenticate')
                ->assertHasFormErrors(['email']);
        }

        // Percobaan ke-6 harus ditolak oleh rate limiter meskipun memakai kode yang benar.
        $sixth = Livewire::test(RecoveryLogin::class)
            ->fillForm([
                'email' => $admin->email,
                'recovery_code' => $codes[0],
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $errorMessage = $sixth->instance()->getErrorBag()->first('data.email');
        $this->assertStringContainsString('Terlalu banyak percobaan', $errorMessage);
        $this->assertFalse(Auth::check());
        $this->assertSame(10, app(RecoveryCodeService::class)->remainingCount($admin));
    }

    public function test_regenerate_command_requires_confirmation_without_force(): void
    {
        [$admin, $oldCodes] = $this->initSuperAdminWithCodes();

        $this->artisan('app:super-admin:recovery-codes:regenerate')
            ->expectsConfirmation(RegenerateSuperAdminRecoveryCodesCommand::CONFIRM_QUESTION, 'no')
            ->assertExitCode(1);

        $this->assertTrue(app(RecoveryCodeService::class)->verifyAndConsume($admin, $oldCodes[0]));
    }

    public function test_regenerate_command_invalidates_old_codes_creates_ten_new_codes_and_records_audit_log(): void
    {
        [$admin, $oldCodes] = $this->initSuperAdminWithCodes();

        Artisan::call('app:super-admin:recovery-codes:regenerate', ['--force' => true]);
        $output = Artisan::output();

        preg_match_all('/RECOVERY_CODE_\d+=([23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{4}(?:-[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{4}){3})/', $output, $matches);
        $newCodes = $matches[1] ?? [];

        $this->assertCount(10, $newCodes);
        $this->assertNotEquals($oldCodes, $newCodes);

        // Kode lama tidak lagi valid.
        $this->assertFalse(app(RecoveryCodeService::class)->verifyAndConsume($admin, $oldCodes[0]));

        // Kode baru valid.
        $this->assertTrue(app(RecoveryCodeService::class)->verifyAndConsume($admin, $newCodes[0]));

        $regenLog = AuditLog::where('action', 'super_admin.recovery_codes_regenerated')->latest('id')->first();
        $usedLog = AuditLog::where('action', 'super_admin.recovery_code_used')->latest('id')->first();

        $this->assertNotNull($regenLog);
        $this->assertNotNull($usedLog);

        $serializedLogs = json_encode([$regenLog->toArray(), $usedLog->toArray()]);
        foreach (array_merge($oldCodes, $newCodes) as $code) {
            $this->assertStringNotContainsString($code, (string) $serializedLogs);
            $this->assertStringNotContainsString(app(RecoveryCodeService::class)->normalize($code), (string) $serializedLogs);
        }
    }

    public function test_custom_profile_displays_remaining_recovery_codes_and_low_code_warning_for_super_admin_only(): void
    {
        [$admin] = $this->initSuperAdminWithCodes();

        $this->actingAs($admin);

        Livewire::test(CustomProfile::class)
            ->assertSee('Kode Pemulihan Darurat')
            ->assertSee('Tersisa 10 dari 10 kode pemulihan sekali pakai.')
            ->assertDontSee('Peringatan: sisa kode pemulihan kurang dari 3');

        // Tandai 8 kode sudah terpakai sehingga tersisa 2 (< 3).
        SuperAdminRecoveryCode::where('user_id', $admin->getKey())
            ->limit(8)
            ->update(['used_at' => now()]);

        Livewire::test(CustomProfile::class)
            ->assertSee('Tersisa 2 dari 10 kode pemulihan sekali pakai.')
            ->assertSee('Peringatan: sisa kode pemulihan kurang dari 3')
            ->assertSee('php artisan app:super-admin:recovery-codes:regenerate');

        // User non-super_admin tidak melihat bagian Kode Pemulihan Darurat.
        $marketing = User::where('email', 'marketing@rs-elisabeth.com')->firstOrFail();
        $marketing->update(['must_change_password' => false]);
        $this->actingAs($marketing);

        Livewire::test(CustomProfile::class)
            ->assertDontSee('Kode Pemulihan Darurat');
    }
}
