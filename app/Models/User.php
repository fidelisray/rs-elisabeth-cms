<?php

namespace App\Models;

use App\Exceptions\UserAccessException;
use App\Models\Pivots\UserRolePivot;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    use HasRoles {
        // roles() milik trait ditimpa di bawah agar memakai pivot penjaga.
        roles as protected spatieRoles;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'must_change_password',
        'is_active',
    ];

    /**
     * Nilai bawaan atribut. Selaras dengan default kolom di database
     * agar model yang baru dibuat langsung berstatus aktif.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Guard aturan akses. Ditegakkan di model agar berlaku untuk semua jalur
     * kode (form, command, tinker), bukan hanya satu halaman.
     */
    protected static function booted(): void
    {
        static::updating(function (User $user) {
            if ($user->isDirty('is_active') && ! $user->is_active && $user->isSuperAdmin()) {
                throw new UserAccessException('Akun super_admin tidak boleh dinonaktifkan.');
            }
        });

        // K-3: akun super_admin tidak boleh dihapus (hanya ada satu dan tidak tergantikan).
        static::deleting(function (User $user) {
            if ($user->isSuperAdmin()) {
                throw new UserAccessException('Akun super_admin tidak boleh dihapus.');
            }
        });
    }

    /**
     * Relasi role dengan pivot penjaga (UserRolePivot) agar aturan satu
     * super_admin berlaku untuk assignRole, syncRoles, removeRole, dan
     * Select relationship Filament.
     */
    public function roles(): MorphToMany
    {
        return $this->spatieRoles()->using(UserRolePivot::class);
    }

    /**
     * Menentukan apakah user ini boleh mengakses panel Filament Admin.
     * Syarat: akun aktif DAN memiliki minimal 1 role/peran.
     * Role tidak dihapus saat akun dinonaktifkan, sehingga pengaktifan kembali
     * langsung memulihkan akses tanpa perlu mengatur ulang role.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->roles()->exists();
    }

    /**
     * Mengecek apakah user adalah Super Admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Mengecek apakah user adalah Staf biasa (Marketing).
     */
    public function isStaff(): bool
    {
        return $this->hasRole('marketing');
    }
}
