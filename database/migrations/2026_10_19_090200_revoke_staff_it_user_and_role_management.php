<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Cabut permission manajemen User dan Role dari role Staff IT yang sudah ada.
     *
     * Seeder hanya memberi permission awal saat role baru dibuat, sehingga
     * database existing yang pernah di-seed dengan konfigurasi lama memerlukan
     * migration pencabutan idempoten ini. Permission konten milik Staff IT
     * tidak diubah.
     */
    public function up(): void
    {
        $tables = config('permission.table_names');

        $role = DB::table($tables['roles'])
            ->where('name', 'Staff IT')
            ->where('guard_name', 'web')
            ->first();

        if (! $role) {
            return;
        }

        $permissionIds = DB::table($tables['permissions'])
            ->where('guard_name', 'web')
            ->where(function ($query): void {
                $query->where('name', 'like', '%:User')
                    ->orWhere('name', 'like', '%:Role');
            })
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table($tables['role_has_permissions'])
                ->where('role_id', $role->id)
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Permission User dan Role tidak dikembalikan ke Staff IT saat rollback
     * karena melanggar prinsip pemisahan hak akses super_admin (K-1, K-2).
     */
    public function down(): void
    {
        //
    }
};
