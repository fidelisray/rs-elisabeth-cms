<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * MVP-1.1: PermissionSeeder berjalan setiap rilis, hanya mengisi permissions,
 * tidak membuat role/akun, tidak menimpa permission role buatan admin, dan
 * secara additive menyinkronkan permission baru ke role super_admin yang sudah ada.
 */
class SeederPermissionPreservationTest extends TestCase
{
    use RefreshDatabase;

    private function seedAll(): void
    {
        $this->seed(DatabaseSeeder::class);
    }

    public function test_fresh_seed_creates_expected_permissions_without_creating_roles(): void
    {
        $this->seedAll();

        $this->assertSame(0, Role::count());
        $this->assertTrue(Permission::where('name', 'Update:News')->exists());
        $this->assertTrue(Permission::where('name', 'Restore:News')->exists());
        $this->assertTrue(Permission::where('name', 'ForceDelete:News')->exists());
        $this->assertTrue(Permission::where('name', 'View:FeedbackSectionWidget')->exists());
        $this->assertTrue(Permission::where('name', 'Update:DynamicFormSubmission')->exists());
    }

    public function test_custom_permission_added_by_admin_survives_reseed(): void
    {
        $this->seedAll();

        $marketing = Role::findOrCreate('marketing', 'web');
        $marketing->givePermissionTo('Delete:News');

        $this->seedAll();

        $this->assertTrue(
            $marketing->fresh()->hasPermissionTo('Delete:News'),
            'Permission tambahan dari admin tidak boleh dihapus seeder.'
        );
    }

    public function test_permission_revoked_by_admin_is_not_restored_by_reseed(): void
    {
        $this->seedAll();

        $marketing = Role::findOrCreate('marketing', 'web');
        $marketing->givePermissionTo(['ViewAny:Article', 'Create:Article']);
        $marketing->revokePermissionTo('Create:Article');

        $this->seedAll();

        $this->assertFalse(
            $marketing->fresh()->hasPermissionTo('Create:Article'),
            'Permission yang dicabut admin tidak boleh dikembalikan seeder.'
        );
    }

    public function test_deleted_custom_role_is_not_recreated_by_reseed(): void
    {
        $this->seedAll();

        $marketing = Role::findOrCreate('marketing', 'web');
        $marketing->delete();

        $this->seedAll();

        $this->assertFalse(Role::where('name', 'marketing')->exists());
    }

    public function test_new_permission_is_added_to_existing_super_admin(): void
    {
        $this->seedAll();
        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])->assertExitCode(0);

        // Permission baru muncul (mis. modul baru) setelah super_admin sudah ada.
        Permission::findOrCreate('View:ModulBaru', 'web');

        $this->seedAll();

        $this->assertTrue(
            Role::findByName('super_admin', 'web')->fresh()->hasPermissionTo('View:ModulBaru'),
            'super_admin harus otomatis mendapat permission baru secara additive.'
        );
    }

    public function test_super_admin_always_has_every_permission_after_init_and_reseed(): void
    {
        $this->seedAll();
        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])->assertExitCode(0);

        $superAdmin = Role::findByName('super_admin', 'web');

        $this->assertSame(Permission::where('guard_name', 'web')->count(), $superAdmin->permissions()->count());

        $this->seedAll();
        $this->seedAll();

        $this->assertSame(Permission::where('guard_name', 'web')->count(), $superAdmin->fresh()->permissions()->count());
    }

    public function test_reseed_twice_does_not_duplicate_permissions(): void
    {
        $this->seedAll();
        $before = Permission::count();

        $this->seedAll();
        $this->seedAll();

        $this->assertSame($before, Permission::count());
    }

    public function test_renamed_widget_permissions_keep_role_and_direct_user_grants(): void
    {
        $this->seedAll();
        $legacy = Permission::findOrCreate('View:FeedbackStatsWidget', 'web');
        $marketing = Role::findOrCreate('marketing', 'web');
        $marketing->givePermissionTo($legacy);
        $user = User::factory()->create(['email' => 'it@rs-elisabeth.com']);
        $user->givePermissionTo($legacy);

        $this->seedAll();
        $this->assertTrue($marketing->fresh()->hasPermissionTo('View:FeedbackSectionWidget'));
        $this->assertTrue($user->fresh()->hasDirectPermission('View:FeedbackSectionWidget'));
        $this->assertFalse(Permission::where('name', 'View:FeedbackStatsWidget')->exists());

        $marketing->revokePermissionTo('View:FeedbackSectionWidget');
        $this->seedAll();
        $this->assertFalse($marketing->fresh()->hasPermissionTo('View:FeedbackSectionWidget'));
    }

    public function test_widget_reconciliation_command_does_not_grant_access_without_a_legacy_grant(): void
    {
        $this->seedAll();
        $marketing = Role::findOrCreate('marketing', 'web');
        $marketing->givePermissionTo('View:QuestionnaireSectionWidget');
        $marketing->revokePermissionTo('View:QuestionnaireSectionWidget');
        $this->artisan('app:permissions:reconcile-widgets')->assertSuccessful();
        $this->assertFalse($marketing->fresh()->hasPermissionTo('View:QuestionnaireSectionWidget'));
    }
}
