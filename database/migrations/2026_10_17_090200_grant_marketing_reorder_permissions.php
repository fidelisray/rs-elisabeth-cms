<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Tambahkan permission reorder ke role marketing yang sudah ada.
     *
     * Seeder hanya memberi permission awal saat role baru dibuat, sehingga
     * database existing membutuhkan migration additive ini. Tidak ada
     * permission lain yang dicabut atau diubah.
     */
    public function up(): void
    {
        $tables = config('permission.table_names');

        $role = DB::table($tables['roles'])
            ->where('name', 'marketing')
            ->where('guard_name', 'web')
            ->first();

        if (! $role) {
            return;
        }

        $permissionIds = DB::table($tables['permissions'])
            ->whereIn('name', [
                'Reorder:BannerPromotion',
                'Reorder:FacilityService',
                'Reorder:RoomFacility',
            ])
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table($tables['role_has_permissions'])->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $role->id,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Permission tidak dicabut saat rollback agar tidak menghilangkan akses
     * yang mungkin sudah dipakai atau disesuaikan admin.
     */
    public function down(): void
    {
        //
    }
};
