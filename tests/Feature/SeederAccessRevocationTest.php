<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * B1/B7: seeder berjalan setiap deploy, sehingga tidak boleh membatalkan
 * keputusan akses yang dibuat admin dan tidak boleh melanggar aturan satu
 * super_admin.
 */
class SeederAccessRevocationTest extends TestCase
{
    use RefreshDatabase;

    private function seedAll(): void
    {
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * super_admin dibuat lewat command (K-6), bukan seeder.
     */
    private function initSuperAdmin(): User
    {
        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])->assertExitCode(0);

        return User::where('email', 'admin@rs-elisabeth.com')->first();
    }

    public function test_fresh_seed_assigns_default_roles_to_new_accounts(): void
    {
        $this->seedAll();
        $this->initSuperAdmin();

        $this->assertTrue(User::where('email', 'admin@rs-elisabeth.com')->first()->hasRole('super_admin'));
        $this->assertTrue(User::where('email', 'marketing@rs-elisabeth.com')->first()->hasRole('marketing'));
        $this->assertTrue(User::where('email', 'it@rs-elisabeth.com')->first()->hasRole('Staff IT'));
    }

    public function test_revoked_role_of_default_account_does_not_come_back_after_reseed(): void
    {
        $this->seedAll();

        $marketing = User::where('email', 'marketing@rs-elisabeth.com')->first();
        $marketing->syncRoles([]);

        $this->seedAll();

        $this->assertFalse(
            $marketing->fresh()->hasRole('marketing'),
            'Role yang dicabut admin tidak boleh dikembalikan oleh seeder.'
        );
        $this->assertSame(0, $marketing->fresh()->roles()->count());
    }

    public function test_extra_role_given_manually_is_not_stripped_by_reseed(): void
    {
        $this->seedAll();

        $it = User::where('email', 'it@rs-elisabeth.com')->first();
        $it->assignRole('marketing');

        $this->seedAll();

        $this->assertTrue($it->fresh()->hasRole('Staff IT'));
        $this->assertTrue($it->fresh()->hasRole('marketing'));
    }

    public function test_staff_user_without_role_stays_without_role_after_reseed(): void
    {
        $this->seedAll();

        $staff = User::factory()->create(['role' => 'staff', 'email' => 'staf.baru@rs-elisabeth.com']);

        $this->seedAll();
        $this->seedAll();

        $this->assertSame(0, $staff->fresh()->roles()->count());
    }

    public function test_seeder_never_grants_super_admin_to_legacy_super_admin_column(): void
    {
        $this->seedAll();
        $this->initSuperAdmin();

        // Dua user lama berkolom role=super_admin, tanpa role Spatie.
        $legacyA = User::factory()->create(['role' => 'super_admin', 'email' => 'lama.a@rs-elisabeth.com']);
        $legacyB = User::factory()->create(['role' => 'super_admin', 'email' => 'lama.b@rs-elisabeth.com']);

        $this->seedAll();

        $this->assertFalse($legacyA->fresh()->hasRole('super_admin'));
        $this->assertFalse($legacyB->fresh()->hasRole('super_admin'));
        $this->assertSame(1, User::role('super_admin')->count(), 'Harus tetap tepat satu super_admin.');
    }

    public function test_existing_default_account_is_not_modified_by_reseed(): void
    {
        $this->seedAll();

        $it = User::where('email', 'it@rs-elisabeth.com')->first();
        $it->update(['name' => 'Nama Diubah Admin', 'is_active' => false]);
        $passwordBefore = $it->fresh()->password;

        $this->seedAll();

        $after = $it->fresh();
        $this->assertSame('Nama Diubah Admin', $after->name);
        $this->assertFalse($after->is_active);
        $this->assertSame($passwordBefore, $after->password);
    }

    public function test_legacy_migration_gives_marketing_once_and_is_idempotent(): void
    {
        $this->seedAll();

        $staff = User::factory()->create(['role' => 'staff', 'email' => 'legacy.staff@rs-elisabeth.com']);
        $this->assertSame(0, $staff->roles()->count());

        $migration = require database_path('migrations/2026_10_20_000002_migrate_legacy_user_roles.php');

        $migration->up();
        $this->assertTrue($staff->fresh()->hasRole('marketing'));
        $this->assertSame(1, $staff->fresh()->roles()->count());

        // Dijalankan ulang: tidak ada perubahan.
        $before = DB::table('model_has_roles')->count();
        $migration->up();
        $this->assertSame($before, DB::table('model_has_roles')->count());
    }

    public function test_legacy_migration_never_grants_super_admin(): void
    {
        $this->seedAll();
        $this->initSuperAdmin();

        $legacy = User::factory()->create(['role' => 'super_admin', 'email' => 'lama@rs-elisabeth.com']);

        $migration = require database_path('migrations/2026_10_20_000002_migrate_legacy_user_roles.php');
        $migration->up();

        $this->assertFalse($legacy->fresh()->hasRole('super_admin'));
        $this->assertSame(1, User::role('super_admin')->count());
    }

    public function test_legacy_migration_does_not_touch_users_that_already_have_a_role(): void
    {
        $this->seedAll();

        $it = User::where('email', 'it@rs-elisabeth.com')->first();
        $it->update(['role' => 'staff']);

        $migration = require database_path('migrations/2026_10_20_000002_migrate_legacy_user_roles.php');
        $migration->up();

        $this->assertTrue($it->fresh()->hasRole('Staff IT'));
        $this->assertFalse($it->fresh()->hasRole('marketing'));
    }

    public function test_artisan_db_seed_twice_keeps_revoked_access(): void
    {
        $this->seedAll();

        $marketing = User::where('email', 'marketing@rs-elisabeth.com')->first();
        $marketing->syncRoles([]);

        Artisan::call('db:seed', ['--force' => true]);
        Artisan::call('db:seed', ['--force' => true]);

        $this->assertSame(0, $marketing->fresh()->roles()->count());
    }
}
