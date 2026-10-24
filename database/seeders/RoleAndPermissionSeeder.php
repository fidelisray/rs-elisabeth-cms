<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Daftar aksi permission standar yang di-generate oleh Filament Shield.
     * Nilai ini harus tetap selaras dengan `policies.methods` di
     * config/filament-shield.php.
     */
    protected const RESOURCE_ACTIONS = [
        'ViewAny',
        'View',
        'Create',
        'Update',
        'Delete',
        'DeleteAny',
        'Restore',
        'ForceDelete',
        'ForceDeleteAny',
        'RestoreAny',
        'Replicate',
        'Reorder',
    ];

    /**
     * Daftar subject konten yang bisa dikelola oleh role marketing.
     * Harus selaras dengan nama model (class basename) di app/Models.
     */
    protected const CONTENT_SUBJECTS = [
        'Article',
        'News',
        'Promotion',
        'BannerPromotion',
        'FacilityService',
        'RoomFacility',
    ];

    /**
     * Profil dokter: data master dikelola SIMRS (read-only), marketing hanya boleh
     * melihat dan mengubah 4 field konten (tanpa create/delete). Lihat DoctorResource.
     */
    protected const DOCTOR_SUBJECT = 'Doctor';

    /**
     * Daftar subject administrasi (hanya boleh diakses oleh super_admin
     * dan Staff IT). Tidak termasuk di role marketing.
     */
    protected const ADMIN_SUBJECTS = [
        'User',
        'Role',
    ];

    /**
     * Gabungan seluruh subject (dipakai untuk super_admin & pembentukan
     * permission secara lengkap).
     */
    protected const ALL_SUBJECTS = [
        'Article',
        'News',
        'Promotion',
        'BannerPromotion',
        'FacilityService',
        'RoomFacility',
        'Doctor',
        'User',
        'Role',
    ];

    /**
     * Subject yang mendukung drag-and-drop sort_order dan boleh diatur Marketing.
     */
    protected const REORDERABLE_SUBJECTS = [
        'BannerPromotion',
        'FacilityService',
        'RoomFacility',
    ];

    /**
     * Nama widget yang dipakai untuk permission widget.
     */
    protected const WIDGET_SUBJECTS = [
        'FeedbackStatsWidget',
        'LatestFeedbackWidget',
    ];

    /**
     * Konstanta nama role & subject agar tidak ada typo/duplikasi magic string.
     */
    protected const ROLE_SUPER_ADMIN = 'super_admin';

    protected const ROLE_MARKETING = 'marketing';

    protected const ROLE_STAFF_IT = 'Staff IT';

    /**
     * Nama role lama (legacy) yang masih mungkin ada pada database yang
     * sebelumnya memakai nama "humas". Role ini akan dimigrasi ke "marketing".
     */
    protected const LEGACY_ROLE_MARKETING = 'humas';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->seedPermissions();

        $superAdminRole = $this->seedSuperAdminRole();
        $marketingRole = $this->seedMarketingRole();
        $staffItRole = $this->seedStaffItRole();

        $this->migrateLegacyMarketingRole($marketingRole);
        $this->assignRolesToUsers($superAdminRole, $marketingRole, $staffItRole);
    }

    /**
     * Pastikan seluruh permission yang dibutuhkan tersedia di database.
     *
     * Ini membuat seeder tidak bergantung pada `php artisan shield:generate`
     * dijalankan terlebih dahulu. Idempoten: memakai findOrCreate.
     */
    protected function seedPermissions(): void
    {
        foreach (self::ALL_SUBJECTS as $subject) {
            foreach (self::RESOURCE_ACTIONS as $action) {
                Permission::findOrCreate("{$action}:{$subject}", 'web');
            }
        }

        foreach (self::WIDGET_SUBJECTS as $subject) {
            Permission::findOrCreate("View:{$subject}", 'web');
        }
    }

    /**
     * Buat role super_admin dan berikan SEMUA permission.
     *
     * Bersifat aditif (givePermissionTo): permission baru dari modul baru
     * otomatis ikut, dan tidak ada permission yang pernah dicabut dari
     * super_admin oleh seeder.
     */
    protected function seedSuperAdminRole(): Role
    {
        $role = Role::findOrCreate(self::ROLE_SUPER_ADMIN, 'web');
        $role->givePermissionTo(Permission::all());

        return $role;
    }

    /**
     * Terapkan set permission awal HANYA bila role baru dibuat.
     *
     * Seeder berjalan pada setiap rilis. Untuk role yang sudah ada, perubahan
     * permission yang dibuat admin lewat UI Shield harus bertahan, jadi seeder
     * tidak menambah maupun mencabut apa pun. Pencabutan permission lama
     * dilakukan lewat migration, bukan seeder.
     *
     * @param  Collection<int, Permission>  $permissions
     */
    protected function seedRoleWithInitialPermissions(string $name, $permissions): Role
    {
        $role = Role::findOrCreate($name, 'web');

        if ($role->wasRecentlyCreated) {
            $role->syncPermissions($permissions);
        }

        return $role;
    }

    /**
     * Buat role marketing dengan akses terbatas:
     * View/Create/Update/Delete (soft delete) konten.
     * Tidak memiliki Restore*, ForceDelete*, maupun akses User & Role (I18N-9.1).
     */
    protected function seedMarketingRole(): Role
    {
        $allowed = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'];
        $names = [];

        // Marketing hanya mengelola konten, TIDAK termasuk User & Role.
        // Restore* dan ForceDelete* tidak diberikan (hanya super_admin, I18N-9).
        foreach (self::CONTENT_SUBJECTS as $subject) {
            foreach ($allowed as $action) {
                $names[] = "{$action}:{$subject}";
            }
        }

        $names[] = 'ViewAny:'.self::DOCTOR_SUBJECT;
        $names[] = 'View:'.self::DOCTOR_SUBJECT;
        $names[] = 'Update:'.self::DOCTOR_SUBJECT;

        foreach (self::REORDERABLE_SUBJECTS as $subject) {
            $names[] = "Reorder:{$subject}";
        }

        foreach (self::WIDGET_SUBJECTS as $subject) {
            $names[] = "View:{$subject}";
        }

        $permissions = Permission::whereIn('name', $names)->get();

        return $this->seedRoleWithInitialPermissions(self::ROLE_MARKETING, $permissions);
    }

    /**
     * Buat role Staff IT: akses View modul konten saja (tanpa User & Role).
     */
    protected function seedStaffItRole(): Role
    {
        $viewOnly = ['ViewAny', 'View'];
        $names = [];

        // Staff IT hanya MELIHAT modul konten. Manajemen User dan Role adalah
        // wewenang super_admin saja (K-1, K-2), jadi ADMIN_SUBJECTS tidak
        // pernah diberikan kepada Staff IT.
        $names[] = 'ViewAny:'.self::DOCTOR_SUBJECT;
        $names[] = 'View:'.self::DOCTOR_SUBJECT;

        foreach (self::CONTENT_SUBJECTS as $subject) {
            foreach ($viewOnly as $action) {
                $names[] = "{$action}:{$subject}";
            }
        }

        foreach (self::WIDGET_SUBJECTS as $subject) {
            $names[] = "View:{$subject}";
        }

        $permissions = Permission::whereIn('name', $names)->get();

        return $this->seedRoleWithInitialPermissions(self::ROLE_STAFF_IT, $permissions);
    }

    /**
     * Migrasi role legacy "humas" menjadi "marketing" (perubahan penamaan).
     *
     * Aman & idempoten:
     * - Memindahkan semua user yang masih memegang role "humas" ke "marketing".
     * - Menyalin permission role "humas" ke "marketing" hanya jika role
     *   "marketing" belum punya permission (untuk preserve custom permission
     *   yang mungkin sudah dibuat admin sebelumnya).
     * - Menghapus role "humas" setelah migrasi selesai.
     */
    protected function migrateLegacyMarketingRole(Role $marketingRole): void
    {
        $legacyRole = Role::where('name', self::LEGACY_ROLE_MARKETING)->first();

        if (! $legacyRole) {
            return;
        }

        // 1. Pindahkan user dari role lama ke role baru (jika belum memilikinya).
        $users = User::whereHas('roles', fn ($q) => $q->where('name', self::LEGACY_ROLE_MARKETING))->get();
        foreach ($users as $user) {
            $user->assignRole($marketingRole);
        }

        // 2. Preserve custom permission lama jika marketing belum punya permission.
        if ($marketingRole->permissions()->count() === 0) {
            $marketingRole->syncPermissions($legacyRole->permissions);
        }

        // 3. Hapus role legacy setelah seluruh user & permission terpindah.
        $legacyRole->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Pasangkan role Spatie ke tiga akun default HANYA saat akun tersebut
     * baru dibuat oleh AdminUserSeeder pada eksekusi ini.
     *
     * Seeder ini berjalan pada setiap deploy. Karena itu ia TIDAK boleh:
     * - mengembalikan role yang sudah dicabut admin (pencabutan akses harus bertahan),
     * - memberi role ke user lain berdasarkan kolom legacy `users.role`
     *   (hal itu pernah melanggar aturan satu super_admin).
     *
     * Data user lama tidak dimigrasi otomatis: go-live memakai database kosong
     * (database pre-production direset lewat `app:preprod:reset`), bukan seeder.
     */
    protected function assignRolesToUsers(Role $superAdminRole, Role $marketingRole, Role $staffItRole): void
    {
        // super_admin sengaja TIDAK ada di sini: akun dan rolenya dibuat oleh
        // php artisan app:super-admin:init agar hanya ada satu super_admin (K-3).
        $assignments = [
            'marketing@rs-elisabeth.com' => $marketingRole,
            'it@rs-elisabeth.com' => $staffItRole,
        ];

        foreach ($assignments as $email => $role) {
            if (! in_array($email, AdminUserSeeder::$createdEmails, true)) {
                continue;
            }

            $user = User::where('email', $email)->first();

            if ($user && ! $user->hasRole($role)) {
                $user->assignRole($role);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
