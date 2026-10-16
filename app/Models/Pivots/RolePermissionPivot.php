<?php

namespace App\Models\Pivots;

use App\Exceptions\UserAccessException;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;

/**
 * Pivot role dan permission (role_has_permissions) yang MENJAGA role super_admin
 * (K-7): permission-nya hanya boleh bertambah, tidak boleh dikurangi.
 *
 * Shield menyimpan permission lewat syncPermissions() yang mencabut lalu memberi
 * ulang lewat query pivot langsung. Penjaga di level pivot menutup jalur itu,
 * termasuk revokePermissionTo() dan pemanggilan dari kode lain.
 */
class RolePermissionPivot extends Pivot
{
    protected static function booted(): void
    {
        static::deleting(function (self $pivot): void {
            $roleKey = config('permission.column_names.role_pivot_key') ?? 'role_id';

            $name = DB::table(config('permission.table_names.roles'))
                ->where('id', $pivot->getAttribute($roleKey))
                ->value('name');

            if ($name === 'super_admin') {
                throw new UserAccessException('Permission role super_admin tidak boleh dikurangi.');
            }
        });
    }
}
