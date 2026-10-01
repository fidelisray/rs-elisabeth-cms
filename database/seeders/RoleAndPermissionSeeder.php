<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Buat peran super_admin dan berikan SEMUA akses
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->givePermissionTo(Permission::all());

        // 2. Buat peran humas dan berikan akses terbatas (bisa View, Create, Update, TIDAK BISA Delete)
        $humas = Role::firstOrCreate(['name' => 'humas', 'guard_name' => 'web']);
        
        $humasPermissions = Permission::whereIn('name', [
            'view_any_article', 'view_article', 'create_article', 'update_article',
            'view_any_news', 'view_news', 'create_news', 'update_news',
            'view_any_promotion', 'view_promotion', 'create_promotion', 'update_promotion',
            'view_any_banner::promotion', 'view_banner::promotion', 'create_banner::promotion', 'update_banner::promotion',
            'view_any_facility::service', 'view_facility::service', 'create_facility::service', 'update_facility::service',
            'view_any_room::facility', 'view_room::facility', 'create_room::facility', 'update_room::facility',
            
            // Akses Widget
            'widget_AccountWidget',
            'widget_FilamentInfoWidget',
            'widget_FeedbackStatsWidget',
            'widget_LatestFeedbackWidget'
        ])->get();
        
        $humas->syncPermissions($humasPermissions);

        // 3. Migrasi semua user 'super_admin' lama ke Spatie super_admin
        $oldSuperAdmins = User::where('role', 'super_admin')->get();
        foreach ($oldSuperAdmins as $user) {
            $user->assignRole('super_admin');
        }

        // 4. Migrasi semua user 'staff' lama ke Spatie humas
        $oldStaffs = User::where('role', 'staff')->get();
        foreach ($oldStaffs as $user) {
            $user->assignRole('humas');
        }
    }
}
