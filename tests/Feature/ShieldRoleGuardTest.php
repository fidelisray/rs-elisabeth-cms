<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Role;
use App\Models\User;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\EditRole;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\ListRoles;
use BezhanSalleh\FilamentShield\Support\Utils;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * K-7 lewat UI Shield sungguhan (Livewire), bukan hanya lewat model:
 * perubahan dari halaman manajemen role tidak boleh merusak role super_admin.
 */
class ShieldRoleGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])->assertExitCode(0);

        $this->admin = User::where('email', 'admin@rs-elisabeth.com')->first();
        $this->admin->update(['must_change_password' => false]);
        $this->actingAs($this->admin);
    }

    public function test_shield_role_resource_uses_the_guarded_role_model(): void
    {
        $this->assertSame(Role::class, Utils::getRoleModel());
        $this->assertInstanceOf(Role::class, Role::findByName('super_admin', 'web'));
    }

    public function test_super_admin_cannot_delete_super_admin_role_from_the_list(): void
    {
        $role = Role::findByName('super_admin', 'web');

        Livewire::test(ListRoles::class)
            ->assertTableActionHidden('delete', $role);

        $this->assertTrue(Role::where('name', 'super_admin')->exists());
    }

    public function test_saving_the_super_admin_role_form_cannot_reduce_its_permissions(): void
    {
        $role = Role::findByName('super_admin', 'web');
        $before = $role->permissions()->count();

        try {
            Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
                ->fillForm(['name' => 'super_admin'])
                ->call('save');
        } catch (\Throwable) {
            // Penolakan lewat exception juga dianggap terlindungi.
        }

        $this->assertSame($before, $role->fresh()->permissions()->count(), 'Menyimpan form role tidak boleh mengurangi permission super_admin.');
    }

    public function test_other_role_can_still_be_edited_through_shield(): void
    {
        $role = Role::findByName('marketing', 'web');

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm(['name' => 'marketing'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Role::where('name', 'marketing')->exists());
    }

    public function test_user_form_does_not_offer_super_admin_role_option(): void
    {
        $options = Filament::getPanel('admin');
        $this->assertNotNull($options);

        $component = Livewire::test(CreateUser::class);
        $select = $component->instance()->getSchema('form')->getFlatComponents()['roles'] ?? null;

        $this->assertNotNull($select, 'Field roles harus ada di form user.');

        $labels = collect($select->getOptions())->map(fn ($l) => strtolower((string) $l))->values();

        $this->assertFalse($labels->contains(fn ($l) => str_contains($l, 'super')), 'Opsi super_admin tidak boleh muncul di form user.');
        $this->assertTrue($labels->contains(fn ($l) => str_contains($l, 'marketing')));
    }
}
