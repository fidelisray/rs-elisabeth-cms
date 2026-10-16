<?php

namespace App\Models;

use App\Exceptions\UserAccessException;
use App\Models\Pivots\RolePermissionPivot;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Role aplikasi. Mengunci role super_admin (K-7):
 * - tidak boleh dihapus,
 * - tidak boleh diganti namanya,
 * - permission-nya hanya boleh bertambah (lihat RolePermissionPivot).
 *
 * Didaftarkan di config/permission.php (models.role) sehingga dipakai Spatie
 * dan Filament Shield. Kode aplikasi harus memakai class ini, bukan
 * Spatie\Permission\Models\Role, agar penjaga selalu berlaku.
 */
class Role extends SpatieRole
{
    public const SUPER_ADMIN = 'super_admin';

    protected static function booted(): void
    {
        static::deleting(function (self $role): void {
            if ($role->name === self::SUPER_ADMIN) {
                throw new UserAccessException('Role super_admin tidak boleh dihapus.');
            }
        });

        static::updating(function (self $role): void {
            $wasSuperAdmin = $role->getOriginal('name') === self::SUPER_ADMIN;

            if ($wasSuperAdmin && ($role->isDirty('name') || $role->isDirty('guard_name'))) {
                throw new UserAccessException('Role super_admin tidak boleh diubah namanya.');
            }
        });
    }

    public function permissions(): BelongsToMany
    {
        return parent::permissions()->using(RolePermissionPivot::class);
    }
}
