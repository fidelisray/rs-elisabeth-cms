<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * MVP-1.3 (K-6, K-18): super_admin dibuat oleh command dengan password acak
 * yang dicetak sekali. Tidak ada password tetap di kode, dan seeder tidak
 * membuat akun maupun role.
 */
class SuperAdminInitCommandTest extends TestCase
{
    use RefreshDatabase;

    private function seedPermissionsOnly(): void
    {
        $this->seed(PermissionSeeder::class);
    }

    public function test_creates_super_admin_role_and_user_with_random_password_and_forced_change(): void
    {
        $this->seedPermissionsOnly();

        $this->assertFalse(Role::where('name', Role::SUPER_ADMIN)->exists());

        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com', '--name' => 'Super Admin'])
            ->assertExitCode(0);

        $role = Role::where('name', Role::SUPER_ADMIN)->where('guard_name', 'web')->first();
        $this->assertNotNull($role);
        $this->assertSame(Permission::where('guard_name', 'web')->count(), $role->permissions()->count());

        $admin = User::where('email', 'admin@rs-elisabeth.com')->first();

        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('super_admin'));
        $this->assertTrue($admin->must_change_password);
        $this->assertTrue($admin->is_active);
        $this->assertSame(1, User::role('super_admin')->count());
    }

    public function test_prints_the_password_once_and_it_actually_works(): void
    {
        $this->seedPermissionsOnly();

        Artisan::call('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com']);
        $output = Artisan::output();

        $this->assertStringContainsString('hanya ditampilkan SEKALI', $output);

        preg_match('/PASSWORD\s*:\s*(\S+)/', $output, $m);
        $this->assertNotEmpty($m[1] ?? null, 'Password harus tercetak di output.');

        $admin = User::where('email', 'admin@rs-elisabeth.com')->first();
        $this->assertTrue(Hash::check($m[1], $admin->password));
        $this->assertGreaterThanOrEqual(20, strlen($m[1]));
    }

    public function test_password_is_random_between_runs(): void
    {
        $this->seedPermissionsOnly();

        Artisan::call('app:super-admin:init', ['--email' => 'a@rs-elisabeth.com']);
        preg_match('/PASSWORD\s*:\s*(\S+)/', Artisan::output(), $first);

        // Akun super_admin tidak boleh dihapus (K-3). Instalasi baru disimulasikan dengan
        // mengosongkan tabel lewat query massal, yang memang tidak diproteksi penjaga.
        DB::table('model_has_roles')->delete();
        DB::table('users')->delete();

        Artisan::call('app:super-admin:init', ['--email' => 'a@rs-elisabeth.com']);
        preg_match('/PASSWORD\s*:\s*(\S+)/', Artisan::output(), $second);

        $this->assertNotSame($first[1], $second[1]);
    }

    public function test_second_run_fails_without_changing_anything(): void
    {
        $this->seedPermissionsOnly();

        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])->assertExitCode(0);
        $before = User::where('email', 'admin@rs-elisabeth.com')->first();
        $hash = $before->password;

        $this->artisan('app:super-admin:init', ['--email' => 'lain@rs-elisabeth.com'])
            ->expectsOutputToContain('sudah ada')
            ->assertExitCode(1);

        $this->assertSame($hash, $before->fresh()->password);
        $this->assertSame(1, User::role('super_admin')->count());
        $this->assertNull(User::where('email', 'lain@rs-elisabeth.com')->first());
    }

    public function test_fails_if_permissions_have_not_been_seeded(): void
    {
        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])
            ->expectsOutputToContain('db:seed')
            ->assertExitCode(1);

        $this->assertSame(0, User::count());
        $this->assertSame(0, Role::count());
    }

    public function test_rejects_invalid_email(): void
    {
        $this->seedPermissionsOnly();

        $this->artisan('app:super-admin:init', ['--email' => 'bukan-email'])
            ->assertExitCode(1);

        $this->assertSame(0, User::count());
        $this->assertSame(0, Role::count());
    }

    public function test_seeder_never_creates_users_or_roles(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, User::count(), 'Seeder tidak boleh membuat akun apa pun.');
        $this->assertSame(0, Role::count(), 'Seeder tidak boleh membuat role apa pun.');
        $this->assertGreaterThan(0, Permission::count(), 'Seeder harus mengisi tabel permissions.');
    }

    public function test_no_known_super_admin_password_remains_in_seeders(): void
    {
        $source = '';
        $files = array_merge(
            glob(database_path('seeders/*.php')) ?: [],
            glob(database_path('factories/*.php')) ?: [],
        );

        foreach ($files as $file) {
            $source .= file_get_contents($file);
        }

        $this->assertStringNotContainsString('admin@123456', $source);
    }
}
