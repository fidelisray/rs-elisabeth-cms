<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * MVP-1.1: seeder berjalan setiap rilis dan tidak boleh menimpa perubahan
 * permission yang dibuat admin lewat UI Shield.
 */
class SeederPermissionPreservationTest extends TestCase
{
    use RefreshDatabase;

    private function seedAll(): void
    {
        $this->seed(DatabaseSeeder::class);
    }

    public function test_fresh_seed_gives_marketing_its_default_permissions(): void
    {
        $this->seedAll();

        $marketing = Role::findByName('marketing', 'web');

        // 7 modul konten x (ViewAny, View, Create, Update, Delete, DeleteAny) = 42,
        // + Reorder untuk 3 modul, + View untuk 2 widget = 47 (I18N-9.1),
        // + Doctor: ViewAny, View, Update = 3; + baca Feedback/Kuesioner = 4 -> 54.
        $this->assertTrue($marketing->hasPermissionTo('Update:News'));
        $this->assertTrue($marketing->hasPermissionTo('Delete:News'));
        $this->assertTrue($marketing->hasPermissionTo('DeleteAny:News'));
        $this->assertTrue($marketing->hasPermissionTo('Update:OnlineLibrary'));
        $this->assertCount(54, $marketing->permissions);
    }

    public function test_marketing_can_only_view_and_update_doctor_profiles(): void
    {
        $this->seedAll();

        $marketing = Role::findByName('marketing', 'web');

        foreach (['ViewAny', 'View', 'Update'] as $action) {
            $this->assertTrue($marketing->hasPermissionTo("{$action}:Doctor"), "Marketing harus memiliki {$action}:Doctor.");
        }

        foreach (['Create', 'Delete', 'DeleteAny', 'Restore', 'ForceDelete'] as $action) {
            $this->assertFalse($marketing->hasPermissionTo("{$action}:Doctor"), "Marketing tidak boleh memiliki {$action}:Doctor.");
        }
    }

    public function test_marketing_never_receives_restore_or_force_delete(): void
    {
        $this->seedAll();

        $marketing = Role::findByName('marketing', 'web');

        foreach (['News', 'Article', 'Promotion', 'BannerPromotion', 'FacilityService', 'RoomFacility'] as $subject) {
            foreach (['Restore', 'RestoreAny', 'ForceDelete', 'ForceDeleteAny'] as $action) {
                $this->assertFalse(
                    $marketing->hasPermissionTo("{$action}:{$subject}"),
                    "Marketing tidak boleh memiliki {$action}:{$subject}."
                );
            }
        }
    }

    public function test_super_admin_holds_restore_and_force_delete_for_content(): void
    {
        $this->seedAll();

        $superAdmin = Role::findByName('super_admin', 'web');

        foreach (['News', 'Article', 'Promotion', 'BannerPromotion', 'FacilityService', 'RoomFacility'] as $subject) {
            $this->assertTrue($superAdmin->hasPermissionTo("Restore:{$subject}"));
            $this->assertTrue($superAdmin->hasPermissionTo("ForceDelete:{$subject}"));
        }
    }

    public function test_custom_permission_added_by_admin_survives_reseed(): void
    {
        $this->seedAll();

        $marketing = Role::findByName('marketing', 'web');
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

        $marketing = Role::findByName('marketing', 'web');
        $marketing->revokePermissionTo('Create:Article');

        $this->seedAll();

        $this->assertFalse(
            $marketing->fresh()->hasPermissionTo('Create:Article'),
            'Permission yang dicabut admin tidak boleh dikembalikan seeder.'
        );
    }

    public function test_missing_role_is_recreated_with_default_permissions(): void
    {
        $this->seedAll();

        Role::findByName('marketing', 'web')->delete();

        $this->seedAll();

        $marketing = Role::findByName('marketing', 'web');
        $this->assertCount(54, $marketing->permissions);
    }

    public function test_new_permission_is_added_to_existing_super_admin(): void
    {
        $this->seedAll();

        // Permission baru muncul (mis. modul baru) setelah super_admin sudah ada.
        Permission::findOrCreate('View:ModulBaru', 'web');

        $this->seedAll();

        $this->assertTrue(
            Role::findByName('super_admin', 'web')->fresh()->hasPermissionTo('View:ModulBaru'),
            'super_admin harus otomatis mendapat permission baru.'
        );
    }

    public function test_super_admin_always_has_every_permission(): void
    {
        $this->seedAll();

        $superAdmin = Role::findByName('super_admin', 'web');

        $this->assertSame(Permission::count(), $superAdmin->permissions()->count());
    }

    public function test_reseed_twice_does_not_change_permission_counts(): void
    {
        $this->seedAll();
        $before = [
            'marketing' => Role::findByName('marketing', 'web')->permissions()->count(),
            'staff' => Role::findByName('Staff IT', 'web')->permissions()->count(),
            'super' => Role::findByName('super_admin', 'web')->permissions()->count(),
        ];

        $this->seedAll();
        $this->seedAll();

        $this->assertSame($before['marketing'], Role::findByName('marketing', 'web')->permissions()->count());
        $this->assertSame($before['staff'], Role::findByName('Staff IT', 'web')->permissions()->count());
        $this->assertSame($before['super'], Role::findByName('super_admin', 'web')->permissions()->count());
    }

    public function test_renamed_widget_permissions_keep_role_and_direct_user_grants(): void
    {
        $this->seedAll();
        $legacy = Permission::findOrCreate('View:FeedbackStatsWidget', 'web');
        $marketing = Role::findByName('marketing', 'web');
        $marketing->revokePermissionTo('View:FeedbackSectionWidget');
        $marketing->givePermissionTo($legacy);
        $user = User::where('email', 'it@rs-elisabeth.com')->firstOrFail();
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
        $marketing = Role::findByName('marketing', 'web');
        $marketing->revokePermissionTo('View:QuestionnaireSectionWidget');
        $this->artisan('app:permissions:reconcile-widgets')->assertSuccessful();
        $this->assertFalse($marketing->fresh()->hasPermissionTo('View:QuestionnaireSectionWidget'));
    }
}
