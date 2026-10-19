<?php

namespace Tests\Feature;

use App\Exceptions\UserAccessException;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserActivationTest extends TestCase
{
    use RefreshDatabase;

    private function panel(): Panel
    {
        return Panel::make()->id('admin');
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    private function actAsSuperAdmin(): User
    {
        $this->seed(DatabaseSeeder::class);
        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])->assertExitCode(0);

        $admin = User::where('email', 'admin@rs-elisabeth.com')->first();
        $admin->update(['must_change_password' => false]);
        $this->actingAs($admin);

        return $admin;
    }

    public function test_active_user_with_role_can_access_panel(): void
    {
        $user = $this->userWithRole('marketing');

        $this->assertTrue($user->is_active);
        $this->assertTrue($user->canAccessPanel($this->panel()));
    }

    public function test_inactive_user_cannot_access_panel_but_keeps_role(): void
    {
        $user = $this->userWithRole('marketing', ['is_active' => false]);

        $this->assertFalse($user->canAccessPanel($this->panel()));
        $this->assertTrue($user->hasRole('marketing'), 'Role tidak boleh hilang saat dinonaktifkan.');
    }

    public function test_reactivating_user_restores_access_without_changing_role(): void
    {
        $user = $this->userWithRole('marketing', ['is_active' => false]);

        $user->update(['is_active' => true]);

        $this->assertTrue($user->fresh()->canAccessPanel($this->panel()));
        $this->assertTrue($user->fresh()->hasRole('marketing'));
    }

    public function test_active_user_without_role_cannot_access_panel(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->is_active);
        $this->assertFalse($user->canAccessPanel($this->panel()));
    }

    public function test_users_created_without_explicit_flag_are_active_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_deactivating_the_only_super_admin_is_rejected(): void
    {
        $admin = $this->userWithRole('super_admin');

        $this->expectException(UserAccessException::class);

        $admin->update(['is_active' => false]);
    }

    public function test_super_admin_remains_active_after_rejected_deactivation(): void
    {
        $admin = $this->userWithRole('super_admin');

        try {
            $admin->update(['is_active' => false]);
        } catch (UserAccessException) {
            // diharapkan
        }

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_deactivating_regular_user_is_allowed(): void
    {
        $user = $this->userWithRole('marketing');

        $user->update(['is_active' => false]);

        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_active_session_is_rejected_after_deactivation(): void
    {
        // User yang sudah mengganti password awalnya (tidak terkena blokir ganti password).
        $user = $this->userWithRole('marketing', ['must_change_password' => false]);

        // Sesi aktif: panel dapat diakses.
        $this->actingAs($user)->get('/admin')->assertOk();

        // Akun dinonaktifkan saat sesi masih berjalan.
        $user->update(['is_active' => false]);

        // Request berikutnya pada sesi yang sama harus ditolak.
        $this->actingAs($user->fresh())->get('/admin')->assertForbidden();
    }

    public function test_super_admin_can_deactivate_and_activate_regular_user_via_table_action(): void
    {
        $this->actAsSuperAdmin();
        $staff = User::where('email', 'marketing@rs-elisabeth.com')->first();

        Livewire::test(ListUsers::class)
            ->assertTableActionVisible('deactivate', $staff)
            ->assertTableActionHidden('activate', $staff)
            ->callTableAction('deactivate', $staff);

        $staff->refresh();
        $this->assertFalse($staff->is_active);
        $this->assertTrue($staff->hasRole('marketing'));

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('deactivate', $staff)
            ->assertTableActionVisible('activate', $staff)
            ->callTableAction('activate', $staff);

        $this->assertTrue($staff->fresh()->is_active);
    }

    public function test_deactivate_and_activate_table_actions_are_hidden_for_self_and_super_admin(): void
    {
        $admin = $this->actAsSuperAdmin();

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('deactivate', $admin)
            ->assertTableActionHidden('activate', $admin);
    }
}
