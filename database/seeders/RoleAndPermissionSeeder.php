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
            'ViewAny:Article', 'View:Article', 'Create:Article', 'Update:Article',
            'ViewAny:News', 'View:News', 'Create:News', 'Update:News',
            'ViewAny:Promotion', 'View:Promotion', 'Create:Promotion', 'Update:Promotion',
            'ViewAny:BannerPromotion', 'View:BannerPromotion', 'Create:BannerPromotion', 'Update:BannerPromotion',
            'ViewAny:FacilityService', 'View:FacilityService', 'Create:FacilityService', 'Update:FacilityService',
            'ViewAny:RoomFacility', 'View:RoomFacility', 'Create:RoomFacility', 'Update:RoomFacility',
            
            // Akses Widget
            'View:FeedbackStatsWidget',
            'View:LatestFeedbackWidget',

            // Fallback nama snake_case jika ada
            'view_any_article', 'view_article', 'create_article', 'update_article',
            'view_any_news', 'view_news', 'create_news', 'update_news',
            'view_any_promotion', 'view_promotion', 'create_promotion', 'update_promotion',
            'view_any_banner::promotion', 'view_banner::promotion', 'create_banner::promotion', 'update_banner::promotion',
            'view_any_facility::service', 'view_facility::service', 'create_facility::service', 'update_facility::service',
            'view_any_room::facility', 'view_room::facility', 'create_room::facility', 'update_room::facility',
            'widget_AccountWidget',
            'widget_FilamentInfoWidget',
            'widget_FeedbackStatsWidget',
            'widget_LatestFeedbackWidget'
        ])->get();
        
        $humas->syncPermissions($humasPermissions);

        // 3. Buat peran Staff IT (Akses View modul + Kelola User & Role)
        $staffIt = Role::firstOrCreate(['name' => 'Staff IT', 'guard_name' => 'web']);
        $staffItPermissions = Permission::whereIn('name', [
            'ViewAny:Article', 'View:Article',
            'ViewAny:BannerPromotion', 'View:BannerPromotion',
            'ViewAny:FacilityService', 'View:FacilityService',
            'ViewAny:News', 'View:News',
            'ViewAny:Promotion', 'View:Promotion',
            'ViewAny:RoomFacility', 'View:RoomFacility',
            'ViewAny:User', 'View:User', 'Create:User', 'Update:User', 'Delete:User', 'DeleteAny:User', 'Restore:User', 'ForceDelete:User', 'ForceDeleteAny:User', 'RestoreAny:User', 'Replicate:User', 'Reorder:User',
            'ViewAny:Role', 'View:Role', 'Create:Role', 'Update:Role', 'Delete:Role', 'DeleteAny:Role', 'Restore:Role', 'ForceDelete:Role', 'ForceDeleteAny:Role', 'RestoreAny:Role', 'Replicate:Role', 'Reorder:Role',
            'View:FeedbackStatsWidget',
            'View:LatestFeedbackWidget',
        ])->get();
        $staffIt->syncPermissions($staffItPermissions);

        // 4. Assign Spatie role ke akun masing-masing
        $admin = User::where('email', 'admin@rs-elisabeth.com')->first();
        if ($admin) {
            $admin->assignRole('super_admin');
        }

        $marketing = User::where('email', 'marketing@rs-elisabeth.com')->first();
        if ($marketing) {
            $marketing->assignRole('humas');
        }

        $it = User::where('email', 'it@rs-elisabeth.com')->first();
        if ($it) {
            $it->assignRole('Staff IT');
        }

        // 5. Migrasi user super_admin lama yang belum memiliki role Spatie
        $oldSuperAdmins = User::where('role', 'super_admin')->get();
        foreach ($oldSuperAdmins as $user) {
            if (! $user->hasRole('super_admin')) {
                $user->assignRole('super_admin');
            }
        }

        // 6. Migrasi user staff umum lainnya (selain marketing & it)
        $otherStaffs = User::where('role', 'staff')
            ->whereNotIn('email', ['marketing@rs-elisabeth.com', 'it@rs-elisabeth.com', 'humas@rs-elisabeth.com'])
            ->get();
        foreach ($otherStaffs as $user) {
            if (! $user->roles()->exists()) {
                $user->assignRole('humas');
            }
        }
    }
}
