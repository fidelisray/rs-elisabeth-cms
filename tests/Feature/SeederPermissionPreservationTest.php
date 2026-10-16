<?php

namespace Tests\Feature;

use App\Models\Role;
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

        $this->assertTrue($marketing->hasPermissionTo('Update:News'));
        $this->assertFalse($marketing->hasPermissionTo('Delete:News'));
        $this->assertCount(26, $marketing->permissions);
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
        $this->assertCount(26, $marketing->permissions);
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
}
