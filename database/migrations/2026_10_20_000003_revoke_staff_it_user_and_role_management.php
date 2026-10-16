<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Mencabut seluruh permission User dan Role dari role "Staff IT" (K-1, K-2).
     *
     * Dilakukan lewat migration, bukan seeder: seeder tidak boleh mencabut
     * permission apa pun karena berjalan di setiap rilis dan akan menimpa
     * keputusan admin. Migration hanya berjalan sekali.
     *
     * Hanya role "Staff IT" yang disentuh, dan hanya permission berakhiran
     * ":User" atau ":Role". Idempoten.
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
            ->where(function ($query) {
                $query->where('name', 'like', '%:User')
                    ->orWhere('name', 'like', '%:Role');
            })
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        DB::table($tables['role_has_permissions'])
            ->where('role_id', $role->id)
            ->whereIn('permission_id', $permissionIds)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Tidak dibalik: mengembalikan permission yang sengaja dicabut
     * akan membuka kembali akses manajemen user bagi Staff IT.
     */
    public function down(): void
    {
        //
    }
};
