<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Mengedit akun lewat halaman Users. Roles pada form tidak memuat super_admin,
 * sehingga mengedit akun super_admin tidak boleh gagal atau mencabut rolenya.
 */
class EditUserSuperAdminTest extends TestCase
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

    public function test_delete_action_is_hidden_for_super_admin_record(): void
    {
        $admin = $this->actAsSuperAdmin();

        Livewire::test(EditUser::class, ['record' => $admin->getKey()])
            ->assertActionHidden('delete');
    }

    public function test_delete_action_is_visible_for_regular_user_record(): void
    {
        $this->actAsSuperAdmin();
        $marketing = User::where('email', 'marketing@rs-elisabeth.com')->first();

        Livewire::test(EditUser::class, ['record' => $marketing->getKey()])
            ->assertActionVisible('delete');
    }

    public function test_super_admin_record_can_be_edited_without_losing_its_role(): void
    {
        $admin = $this->actAsSuperAdmin();

        Livewire::test(EditUser::class, ['record' => $admin->getKey()])
            ->fillForm(['name' => 'Nama Super Admin Baru'])
            ->call('save')
            ->assertHasNoFormErrors();

        $admin->refresh();
        $this->assertSame('Nama Super Admin Baru', $admin->name);
        $this->assertTrue($admin->hasRole('super_admin'));
        $this->assertSame(1, User::role('super_admin')->count());
    }
}
