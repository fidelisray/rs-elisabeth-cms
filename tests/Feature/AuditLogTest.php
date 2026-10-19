<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\CustomProfile;
use App\Filament\Pages\Auth\ForcePasswordChange;
use App\Filament\Pages\Auth\RecoveryLogin;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * MVP-3.6: audit minimal untuk pembuatan super_admin, perubahan password,
 * reset password CLI, pemakaian recovery code, dan regenerasi recovery code.
 * Password maupun recovery code (plaintext atau hash) tidak boleh masuk ke audit log.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function seedRolesOnly(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function initAdmin(): User
    {
        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])->assertExitCode(0);
        $admin = User::where('email', 'admin@rs-elisabeth.com')->first();
        $admin->update(['must_change_password' => false]);

        return $admin;
    }

    public function test_audit_logs_table_exists_with_no_password_columns(): void
    {
        $this->assertTrue(Schema::hasTable('audit_logs'));
        $columns = Schema::getColumnListing('audit_logs');
        $this->assertNotContains('password', $columns);
        $this->assertNotContains('password_hash', $columns);
        $this->assertNotContains('recovery_code', $columns);
        $this->assertNotContains('code_hash', $columns);
    }

    public function test_super_admin_init_creates_audit_record_without_password(): void
    {
        $this->seedRolesOnly();

        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])->assertExitCode(0);

        $audit = AuditLog::where('action', 'super_admin.created')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame('admin@rs-elisabeth.com', $audit->subject_email);
        $this->assertSame('console', $audit->actor_type);
        $this->assertArrayNotHasKey('password', $audit->metadata ?? []);
        $this->assertArrayNotHasKey('password_hash', $audit->metadata ?? []);
    }

    public function test_forced_password_change_creates_audit_record_without_password(): void
    {
        $this->seedRolesOnly();
        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])->assertExitCode(0);
        $user = User::where('email', 'admin@rs-elisabeth.com')->first();
        $this->actingAs($user);

        Livewire::test(ForcePasswordChange::class)
            ->fillForm(['password' => 'PasswordBaru#2026', 'passwordConfirmation' => 'PasswordBaru#2026'])
            ->call('save')
            ->assertHasNoFormErrors();

        $audit = AuditLog::where('action', 'super_admin.password_changed')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame((string) $user->id, (string) $audit->subject_id);
        $this->assertSame($user->email, $audit->subject_email);
        $this->assertArrayNotHasKey('password', $audit->metadata ?? []);
        $this->assertArrayNotHasKey('password_hash', $audit->metadata ?? []);
        $this->assertStringNotContainsString('PasswordBaru#2026', json_encode($audit->toArray()));
    }

    public function test_super_admin_profile_password_change_creates_audit_record(): void
    {
        $this->seedRolesOnly();
        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])->assertExitCode(0);
        $user = User::where('email', 'admin@rs-elisabeth.com')->first();
        $user->update(['must_change_password' => false]);
        $this->actingAs($user);

        Livewire::test(CustomProfile::class)
            ->fillForm([
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'ProfileBaru#2026',
                'passwordConfirmation' => 'ProfileBaru#2026',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $audit = AuditLog::where('action', 'super_admin.password_changed')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame((string) $user->id, (string) $audit->subject_id);
        $this->assertStringNotContainsString('ProfileBaru#2026', json_encode($audit->toArray()));
        $this->assertTrue(Hash::check('ProfileBaru#2026', $user->fresh()->password));
    }

    public function test_repeated_password_changes_create_separate_audit_records(): void
    {
        $this->seedRolesOnly();
        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])->assertExitCode(0);
        $user = User::where('email', 'admin@rs-elisabeth.com')->first();
        $this->actingAs($user);

        Livewire::test(ForcePasswordChange::class)
            ->fillForm(['password' => 'PasswordSatu#2026', 'passwordConfirmation' => 'PasswordSatu#2026'])
            ->call('save');

        Livewire::test(CustomProfile::class)
            ->fillForm([
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'PasswordDua#2026',
                'passwordConfirmation' => 'PasswordDua#2026',
            ])
            ->call('save');

        $this->assertSame(2, AuditLog::where('action', 'super_admin.password_changed')
            ->where('subject_id', $user->id)->count());
    }

    public function test_all_five_super_admin_actions_are_audited_without_leaking_passwords_or_recovery_codes(): void
    {
        $this->seedRolesOnly();

        // 1. super_admin.created
        $exitCode = Artisan::call('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com']);
        $this->assertSame(0, $exitCode);
        $initOutput = Artisan::output();

        preg_match('/PASSWORD=([A-Za-z0-9]{24})/', $initOutput, $pwMatch);
        preg_match_all('/RECOVERY_CODE_\d+=([A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4})/', $initOutput, $codeMatches);
        $initPassword = $pwMatch[1];
        $initialCodes = $codeMatches[1];

        $user = User::where('email', 'admin@rs-elisabeth.com')->firstOrFail();
        $initPasswordHash = $user->password;

        // 2. super_admin.recovery_code_used
        Livewire::test(RecoveryLogin::class)
            ->set('data.email', 'admin@rs-elisabeth.com')
            ->set('data.recovery_code', $initialCodes[0])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        // 3. super_admin.password_changed
        $this->actingAs($user->fresh());
        Livewire::test(ForcePasswordChange::class)
            ->fillForm(['password' => 'PasswordGanti#2026', 'passwordConfirmation' => 'PasswordGanti#2026'])
            ->call('save')
            ->assertHasNoFormErrors();
        $changedPasswordHash = $user->fresh()->password;

        // 4. super_admin.recovery_codes_regenerated
        $regenExit = Artisan::call('app:super-admin:recovery-codes:regenerate', ['--force' => true]);
        $this->assertSame(0, $regenExit);
        $regenOutput = Artisan::output();
        preg_match_all('/RECOVERY_CODE_\d+=([A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4})/', $regenOutput, $newCodeMatches);
        $regeneratedCodes = $newCodeMatches[1];

        // 5. super_admin.password_reset
        $resetExit = Artisan::call('app:super-admin:reset-password', ['--force' => true]);
        $this->assertSame(0, $resetExit);
        $resetOutput = Artisan::output();
        preg_match('/PASSWORD=([A-Za-z0-9]{24})/', $resetOutput, $resetPwMatch);
        $resetPassword = $resetPwMatch[1];
        $resetPasswordHash = $user->fresh()->password;

        $expectedActions = [
            'super_admin.created',
            'super_admin.recovery_code_used',
            'super_admin.password_changed',
            'super_admin.recovery_codes_regenerated',
            'super_admin.password_reset',
        ];

        foreach ($expectedActions as $action) {
            $this->assertTrue(
                AuditLog::where('action', $action)->exists(),
                "Action {$action} wajib tercatat di audit_logs."
            );
        }

        $allAuditsPayload = json_encode(AuditLog::all()->toArray());
        $secrets = array_merge(
            [$initPassword, $initPasswordHash, 'PasswordGanti#2026', $changedPasswordHash, $resetPassword, $resetPasswordHash],
            $initialCodes,
            array_map(fn (string $c): string => str_replace('-', '', $c), $initialCodes),
            $regeneratedCodes,
            array_map(fn (string $c): string => str_replace('-', '', $c), $regeneratedCodes)
        );

        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString($secret, $allAuditsPayload);
        }
    }
}
