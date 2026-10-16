<?php

namespace App\Models\Pivots;

use App\Exceptions\UserAccessException;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Support\Facades\DB;

/**
 * Pivot penghubung user dan role (model_has_roles) yang MENJAGA aturan satu
 * super_admin (K-3).
 *
 * Mengapa pivot, bukan observer User: assignRole(), syncRoles(), removeRole(),
 * dan Select relationship milik Filament memakai query pivot langsung dan tidak
 * memicu event model User. Dengan memasang class ini lewat `->using()`, setiap
 * attach dan detach melewati event model pivot, sehingga aturan berlaku untuk
 * SEMUA jalur: form, command, kode, dan tinker.
 *
 * Batasan: query massal (DB::table / delete() massal) tetap tidak melewati event.
 */
class UserRolePivot extends MorphPivot
{
    protected static function booted(): void
    {
        static::creating(function (self $pivot): void {
            if (! $pivot->isSuperAdminRole()) {
                return;
            }

            $holders = DB::table($pivot->getTable())
                ->where(self::roleKey(), $pivot->getAttribute(self::roleKey()))
                ->count();

            if ($holders > 0) {
                throw new UserAccessException('Hanya boleh ada satu akun dengan role super_admin.');
            }
        });

        static::deleting(function (self $pivot): void {
            if ($pivot->isSuperAdminRole()) {
                throw new UserAccessException('Role super_admin tidak boleh dicabut dari akunnya.');
            }
        });
    }

    private static function roleKey(): string
    {
        return config('permission.column_names.role_pivot_key') ?? 'role_id';
    }

    private function isSuperAdminRole(): bool
    {
        $name = DB::table(config('permission.table_names.roles'))
            ->where('id', $this->getAttribute(self::roleKey()))
            ->value('name');

        return $name === 'super_admin';
    }
}
