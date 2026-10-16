<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * K-9: password sementara untuk marketing dan IT adalah 123456 dan wajib diganti.
 * Reset hanya dilakukan super_admin, dan tidak tersedia untuk akun super_admin.
 */
class UserPasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actAsSuperAdmin(): User
    {
        $this->seed(DatabaseSeeder::class);
        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])->assertExitCode(0);

        $admin = User::where('email', 'admin@rs-elisabeth.com')->first();
        $admin->update(['must_change_password' => false]);
        $this->actingAs($admin);

        return $admin;
    }

    public function test_new_user_gets_default_password_and_must_change_it(): void
    {
        $this->actAsSuperAdmin();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Staf Baru',
                'email' => 'baru@rs-elisabeth.com',
                'roles' => [Role::where('name', 'marketing')->first()->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'baru@rs-elisabeth.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('123456', $user->password));
        $this->assertTrue($user->must_change_password, 'User baru wajib mengganti password.');
        $this->assertTrue($user->is_active);
    }

    public function test_new_user_with_explicit_password_still_must_change_it(): void
    {
        $this->actAsSuperAdmin();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Staf Dua',
                'email' => 'dua@rs-elisabeth.com',
                'password' => 'DiisiAdmin#2026',
                'roles' => [Role::where('name', 'marketing')->first()->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'dua@rs-elisabeth.com')->first();

        $this->assertTrue($user->must_change_password, 'Password yang ditetapkan admin tetap wajib diganti pemiliknya.');
    }

    public function test_reset_sets_default_password_and_forces_change(): void
    {
        $this->actAsSuperAdmin();

        $staff = User::where('email', 'marketing@rs-elisabeth.com')->first();
        $staff->update(['password' => 'SudahDiganti#2026', 'must_change_password' => false]);

        Livewire::test(ListUsers::class)
            ->callTableAction('resetPassword', $staff);

        $fresh = $staff->fresh();
        $this->assertTrue(Hash::check('123456', $fresh->password));
        $this->assertTrue($fresh->must_change_password);
    }

    public function test_reset_action_is_not_offered_for_super_admin_account(): void
    {
        $admin = $this->actAsSuperAdmin();

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('resetPassword', $admin);
    }

    public function test_reset_action_is_offered_for_regular_staff(): void
    {
        $this->actAsSuperAdmin();

        $staff = User::where('email', 'it@rs-elisabeth.com')->first();

        Livewire::test(ListUsers::class)
            ->assertTableActionVisible('resetPassword', $staff);
    }

    public function test_reset_message_does_not_mislead_about_super_admin(): void
    {
        $this->actAsSuperAdmin();

        $staff = User::where('email', 'it@rs-elisabeth.com')->first();

        Livewire::test(ListUsers::class)
            ->callTableAction('resetPassword', $staff)
            ->assertNotified('Password Berhasil Direset');
    }
}
