<?php

namespace Tests\Feature\Console;

use App\Console\Commands\ResetSuperAdminPasswordCommand;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * MVP-1.4 (K-5, K-23): command reset password super_admin melalui terminal.
 */
class ResetSuperAdminPasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    private function createActiveSuperAdmin(): array
    {
        $this->seed(RoleAndPermissionSeeder::class);

        Artisan::call('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com']);
        preg_match('/PASSWORD=(\S+)/', Artisan::output(), $matches);
        $initialPassword = $matches[1];

        $admin = User::where('email', 'admin@rs-elisabeth.com')->firstOrFail();
        $admin->update(['must_change_password' => false]);

        return [$admin->fresh(), $initialPassword];
    }

    public function test_fails_when_no_super_admin_exists(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $this->artisan('app:super-admin:reset-password', ['--force' => true])
            ->expectsOutputToContain('super_admin')
            ->assertExitCode(1);

        $this->assertSame(0, AuditLog::where('action', 'super_admin.password_reset')->count());
    }

    public function test_fails_when_super_admin_is_inactive(): void
    {
        [$admin, $oldPassword] = $this->createActiveSuperAdmin();

        // Simulasikan kondisi tidak normal di level tabel karena model menolak penonaktifan super_admin.
        DB::table('users')->where('id', $admin->getKey())->update(['is_active' => false]);

        $this->artisan('app:super-admin:reset-password', ['--force' => true])
            ->expectsOutputToContain('nonaktif')
            ->assertExitCode(1);

        $this->assertTrue(Hash::check($oldPassword, $admin->fresh()->password));
        $this->assertSame(0, AuditLog::where('action', 'super_admin.password_reset')->count());
    }

    public function test_declined_confirmation_aborts_without_changes(): void
    {
        [$admin, $oldPassword] = $this->createActiveSuperAdmin();

        DB::table('sessions')->insert([
            'id' => 'sesi-admin-1',
            'user_id' => $admin->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'data',
            'last_activity' => time(),
        ]);

        $this->artisan('app:super-admin:reset-password')
            ->expectsConfirmation(ResetSuperAdminPasswordCommand::CONFIRM_QUESTION, 'no')
            ->assertExitCode(1);

        $fresh = $admin->fresh();
        $this->assertTrue(Hash::check($oldPassword, $fresh->password));
        $this->assertFalse($fresh->must_change_password);
        $this->assertSame(1, DB::table('sessions')->where('user_id', $admin->getKey())->count());
        $this->assertSame(0, AuditLog::where('action', 'super_admin.password_reset')->count());
    }

    public function test_resets_password_with_force_clears_sessions_and_records_audit_log_without_password(): void
    {
        [$admin, $oldPassword] = $this->createActiveSuperAdmin();

        DB::table('sessions')->insert([
            [
                'id' => 'sesi-admin-1',
                'user_id' => $admin->getKey(),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => 'data-1',
                'last_activity' => time(),
            ],
            [
                'id' => 'sesi-admin-2',
                'user_id' => $admin->getKey(),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => 'data-2',
                'last_activity' => time(),
            ],
        ]);

        Artisan::call('app:super-admin:reset-password', ['--force' => true]);
        $output = Artisan::output();

        preg_match('/PASSWORD=(\S+)/', $output, $matches);
        $newPassword = $matches[1] ?? '';

        $this->assertNotEmpty($newPassword, 'Password baru harus dicetak di output terminal.');
        $this->assertSame(24, strlen($newPassword));
        $this->assertNotSame($oldPassword, $newPassword);

        $fresh = $admin->fresh();
        $this->assertFalse(Hash::check($oldPassword, $fresh->password), 'Password lama tidak boleh lagi valid.');
        $this->assertTrue(Hash::check($newPassword, $fresh->password), 'Password baru harus valid.');
        $this->assertTrue($fresh->must_change_password, 'must_change_password harus disetel ke true.');

        $this->assertSame(0, DB::table('sessions')->where('user_id', $admin->getKey())->count(), 'Seluruh sesi milik super_admin harus dihapus.');

        $log = AuditLog::where('action', 'super_admin.password_reset')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame((string) $admin->getKey(), (string) $log->subject_id);
        $this->assertSame('cli', $log->metadata['source'] ?? null);

        $serializedLog = json_encode($log->toArray());
        $this->assertStringNotContainsString($newPassword, (string) $serializedLog);
        $this->assertStringNotContainsString($fresh->password, (string) $serializedLog);
    }

    public function test_user_logging_in_after_reset_is_redirected_to_force_password_change(): void
    {
        [$admin] = $this->createActiveSuperAdmin();

        $this->artisan('app:super-admin:reset-password', ['--force' => true])
            ->assertExitCode(0);

        $this->actingAs($admin->fresh())
            ->get('/admin')
            ->assertRedirect(route('filament.admin.pages.force-password-change'));
    }
}
