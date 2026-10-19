<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * MVP-1.3 (K-6, K-18): super_admin dibuat oleh command dengan password acak
 * yang dicetak sekali. Tidak ada password tetap di kode.
 */
class SuperAdminInitCommandTest extends TestCase
{
    use RefreshDatabase;

    private function seedRolesOnly(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_creates_super_admin_with_random_password_and_forced_change(): void
    {
        $this->seedRolesOnly();

        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com', '--name' => 'Super Admin'])
            ->assertExitCode(0);

        $admin = User::where('email', 'admin@rs-elisabeth.com')->first();

        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('super_admin'));
        $this->assertTrue($admin->must_change_password);
        $this->assertTrue($admin->is_active);
        $this->assertSame(1, User::role('super_admin')->count());
    }

    public function test_prints_the_password_once_and_it_actually_works(): void
    {
        $this->seedRolesOnly();

        Artisan::call('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com']);
        $output = Artisan::output();

        $this->assertStringContainsString('hanya ditampilkan SEKALI', $output);

        preg_match('/PASSWORD=(\S+)/', $output, $m);
        $this->assertNotEmpty($m[1] ?? null, 'Password harus tercetak di output.');

        $admin = User::where('email', 'admin@rs-elisabeth.com')->first();
        $this->assertTrue(Hash::check($m[1], $admin->password));
        $this->assertGreaterThanOrEqual(20, strlen($m[1]));
    }

    public function test_password_is_random_between_runs(): void
    {
        $this->seedRolesOnly();

        Artisan::call('app:super-admin:init', ['--email' => 'a@rs-elisabeth.com']);
        preg_match('/PASSWORD=(\S+)/', Artisan::output(), $first);

        // Akun super_admin tidak boleh dihapus (K-3). Instalasi baru disimulasikan dengan
        // mengosongkan tabel lewat query massal, yang memang tidak diproteksi penjaga.
        DB::table('model_has_roles')->delete();
        DB::table('users')->delete();

        Artisan::call('app:super-admin:init', ['--email' => 'a@rs-elisabeth.com']);
        preg_match('/PASSWORD=(\S+)/', Artisan::output(), $second);

        $this->assertNotSame($first[1], $second[1]);
    }

    public function test_second_run_fails_without_changing_anything(): void
    {
        $this->seedRolesOnly();

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

    public function test_fails_if_roles_have_not_been_seeded(): void
    {
        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])
            ->expectsOutputToContain('db:seed')
            ->assertExitCode(1);

        $this->assertSame(0, User::count());
    }

    public function test_rejects_invalid_email(): void
    {
        $this->seedRolesOnly();

        $this->artisan('app:super-admin:init', ['--email' => 'bukan-email'])
            ->assertExitCode(1);

        $this->assertSame(0, User::role('super_admin')->count());
    }

    public function test_seeder_never_creates_super_admin_or_known_password(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, User::role('super_admin')->count(), 'Seeder tidak boleh membuat super_admin.');
        $this->assertNull(User::where('email', 'admin@rs-elisabeth.com')->first());
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

    public function test_marketing_and_it_accounts_use_default_password_and_must_change_it(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['marketing@rs-elisabeth.com', 'it@rs-elisabeth.com'] as $email) {
            $user = User::where('email', $email)->first();

            $this->assertTrue(Hash::check('123456', $user->password), "$email harus memakai password default K-9.");
            $this->assertTrue($user->must_change_password, "$email wajib ganti password.");
        }
    }
}
