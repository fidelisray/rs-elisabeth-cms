<?php

namespace Tests\Feature;

use App\Exceptions\UserAccessException;
use App\Models\Role;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * MVP-2.1 (K-1, K-2, K-3, K-7): manajemen User dan Role hanya untuk super_admin,
 * hanya ada satu super_admin, dan role super_admin dikunci.
 */
class UserRoleManagementRestrictionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $it;

    private User $marketing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])->assertExitCode(0);

        $this->admin = User::where('email', 'admin@rs-elisabeth.com')->first();
        $this->it = User::where('email', 'it@rs-elisabeth.com')->first();
        $this->marketing = User::where('email', 'marketing@rs-elisabeth.com')->first();

        foreach ([$this->admin, $this->it, $this->marketing] as $user) {
            $user->update(['must_change_password' => false]);
        }
    }

    // ---------------------------------------------------------------- K-1, K-2

    public function test_staff_it_has_no_user_or_role_permissions_after_seed(): void
    {
        $names = Role::findByName('Staff IT', 'web')->permissions->pluck('name');

        $this->assertSame(
            [],
            $names->filter(fn ($n) => str_ends_with($n, ':User') || str_ends_with($n, ':Role'))->values()->all(),
            'Staff IT tidak boleh memegang permission User atau Role.'
        );
    }

    public function test_staff_it_still_can_view_content_modules(): void
    {
        $role = Role::findByName('Staff IT', 'web');

        foreach (['News', 'Article', 'Promotion', 'BannerPromotion', 'FacilityService', 'RoomFacility'] as $subject) {
            $this->assertTrue($role->hasPermissionTo("ViewAny:{$subject}"));
            $this->assertTrue($role->hasPermissionTo("View:{$subject}"));
            $this->assertFalse($role->hasPermissionTo("Update:{$subject}"));
        }
    }

    public function test_staff_it_cannot_open_user_or_role_pages(): void
    {
        $this->actingAs($this->it);

        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/users/create')->assertForbidden();
        $this->get('/admin/shield/roles')->assertForbidden();
        $this->get('/admin/shield/roles/create')->assertForbidden();
    }

    public function test_marketing_cannot_open_user_or_role_pages(): void
    {
        $this->actingAs($this->marketing);

        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/shield/roles')->assertForbidden();
    }

    public function test_super_admin_can_open_user_and_role_pages(): void
    {
        $this->actingAs($this->admin);

        $this->get('/admin/users')->assertOk();
        $this->get('/admin/shield/roles')->assertOk();
    }

    public function test_policies_deny_everyone_except_super_admin(): void
    {
        $userPolicy = new UserPolicy;
        $rolePolicy = new RolePolicy;
        $role = Role::findByName('marketing', 'web');

        foreach ([$this->it, $this->marketing] as $actor) {
            $this->assertFalse($userPolicy->viewAny($actor));
            $this->assertFalse($userPolicy->create($actor));
            $this->assertFalse($userPolicy->update($actor));
            $this->assertFalse($userPolicy->delete($actor));
            $this->assertFalse($rolePolicy->viewAny($actor));
            $this->assertFalse($rolePolicy->create($actor));
            $this->assertFalse($rolePolicy->update($actor, $role));
            $this->assertFalse($rolePolicy->delete($actor, $role));
        }

        $this->assertTrue($userPolicy->viewAny($this->admin));
        $this->assertTrue($userPolicy->create($this->admin));
        $this->assertTrue($rolePolicy->viewAny($this->admin));
        $this->assertTrue($rolePolicy->create($this->admin));
    }

    public function test_policy_denies_even_if_permission_is_granted_directly(): void
    {
        // Kesalahan konfigurasi permission tidak boleh membuka akses (pertahanan berlapis).
        $this->it->givePermissionTo(['ViewAny:User', 'Create:User', 'Update:User', 'ViewAny:Role', 'Update:Role']);

        $policy = new UserPolicy;
        $this->assertFalse($policy->viewAny($this->it->fresh()));
        $this->assertFalse($policy->create($this->it->fresh()));

        $this->actingAs($this->it->fresh())->get('/admin/users')->assertForbidden();
    }

    public function test_reseed_does_not_give_staff_it_user_permissions_back(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $names = Role::findByName('Staff IT', 'web')->permissions->pluck('name');
        $this->assertSame([], $names->filter(fn ($n) => str_ends_with($n, ':User') || str_ends_with($n, ':Role'))->values()->all());
    }

    // -------------------------------------------------------------------- K-3

    public function test_cannot_assign_super_admin_role_to_a_second_user(): void
    {
        $this->expectException(UserAccessException::class);

        $this->it->assignRole('super_admin');
    }

    public function test_second_user_stays_without_super_admin_after_rejected_assignment(): void
    {
        try {
            $this->marketing->assignRole('super_admin');
        } catch (UserAccessException) {
            // diharapkan
        }

        $this->assertFalse($this->marketing->fresh()->hasRole('super_admin'));
        $this->assertSame(1, User::role('super_admin')->count());
    }

    public function test_cannot_sync_roles_to_give_super_admin_to_another_user(): void
    {
        $this->expectException(UserAccessException::class);

        $this->it->syncRoles(['Staff IT', 'super_admin']);
    }

    public function test_cannot_remove_super_admin_role_from_the_only_super_admin(): void
    {
        $this->expectException(UserAccessException::class);

        $this->admin->removeRole('super_admin');
    }

    public function test_cannot_sync_away_the_only_super_admin_role(): void
    {
        try {
            $this->admin->syncRoles(['marketing']);
            $this->fail('syncRoles yang mencabut super_admin tunggal harus ditolak.');
        } catch (UserAccessException) {
            $this->assertTrue($this->admin->fresh()->hasRole('super_admin'));
        }
    }

    public function test_cannot_delete_the_super_admin_user(): void
    {
        $this->expectException(UserAccessException::class);

        $this->admin->delete();
    }

    public function test_super_admin_user_still_exists_after_rejected_delete(): void
    {
        try {
            $this->admin->delete();
        } catch (UserAccessException) {
            // diharapkan
        }

        $this->assertNotNull(User::find($this->admin->id));
    }

    public function test_regular_roles_can_still_be_assigned_and_changed(): void
    {
        $this->it->assignRole('marketing');
        $this->assertTrue($this->it->fresh()->hasRole('marketing'));

        $this->it->removeRole('marketing');
        $this->assertFalse($this->it->fresh()->hasRole('marketing'));
    }

    public function test_regular_user_can_still_be_deleted(): void
    {
        $this->marketing->delete();

        $this->assertNull(User::find($this->marketing->id));
    }

    // -------------------------------------------------------------------- K-7

    public function test_super_admin_role_cannot_be_deleted(): void
    {
        $this->expectException(UserAccessException::class);

        Role::findByName('super_admin', 'web')->delete();
    }

    public function test_super_admin_role_still_exists_after_rejected_delete(): void
    {
        try {
            Role::findByName('super_admin', 'web')->delete();
        } catch (UserAccessException) {
            // diharapkan
        }

        $this->assertTrue(Role::where('name', 'super_admin')->exists());
    }

    public function test_super_admin_role_permissions_cannot_be_reduced(): void
    {
        $role = Role::findByName('super_admin', 'web');

        $this->expectException(UserAccessException::class);

        $role->revokePermissionTo('Update:News');
    }

    public function test_super_admin_role_cannot_be_given_a_subset_of_permissions(): void
    {
        $role = Role::findByName('super_admin', 'web');
        $total = Permission::count();

        try {
            $role->syncPermissions(['View:News']);
            $this->fail('syncPermissions yang mengurangi permission super_admin harus ditolak.');
        } catch (UserAccessException) {
            $this->assertSame($total, $role->fresh()->permissions()->count());
        }
    }

    public function test_super_admin_role_cannot_be_renamed(): void
    {
        $role = Role::findByName('super_admin', 'web');

        $this->expectException(UserAccessException::class);

        $role->update(['name' => 'bukan_super_admin']);
    }

    public function test_super_admin_role_can_still_gain_new_permissions(): void
    {
        $role = Role::findByName('super_admin', 'web');
        Permission::findOrCreate('View:ModulBaru', 'web');

        $role->givePermissionTo('View:ModulBaru');

        $this->assertTrue($role->fresh()->hasPermissionTo('View:ModulBaru'));
    }

    public function test_other_roles_can_still_be_edited_and_deleted(): void
    {
        $custom = Role::findOrCreate('kustom', 'web');
        $custom->givePermissionTo('View:News');
        $custom->revokePermissionTo('View:News');
        $custom->delete();

        $this->assertFalse(Role::where('name', 'kustom')->exists());
    }
}
