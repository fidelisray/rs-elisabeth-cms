<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
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

        $staff = User::factory()->create(['email' => 'staf.baru@rs-elisabeth.com']);

        $this->seedAll();
        $this->seedAll();

        $this->assertSame(0, $staff->fresh()->roles()->count());
    }

    public function test_seeder_never_grants_super_admin_to_extra_users_and_legacy_role_column_is_dropped(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'role'), 'Kolom legacy users.role harus sudah dihapus.');

        $this->seedAll();
        $this->initSuperAdmin();

        $extraA = User::factory()->create(['email' => 'lama.a@rs-elisabeth.com']);
        $extraB = User::factory()->create(['email' => 'lama.b@rs-elisabeth.com']);

        $this->seedAll();

        $this->assertFalse($extraA->fresh()->hasRole('super_admin'));
        $this->assertFalse($extraB->fresh()->hasRole('super_admin'));
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
