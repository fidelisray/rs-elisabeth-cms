<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Migrasi data sekali jalan untuk user lama.
     *
     * Sebelumnya logika ini ada di seeder dan berjalan di setiap boot, sehingga
     * mengembalikan role yang sudah dicabut admin. Kini dipindahkan ke migration:
     * tercatat di tabel migrations dan tidak pernah berulang.
     *
     * Aturan:
     * - Hanya user berkolom `role = 'staff'` yang belum memiliki role Spatie
     *   apa pun yang diberi role `marketing`.
     * - TIDAK PERNAH memberi `super_admin` kepada siapa pun (aturan satu super_admin).
     * - Idempoten: user yang sudah memiliki role tidak disentuh.
     */
    public function up(): void
    {
        $tables = config('permission.table_names');

        $role = DB::table($tables['roles'])
            ->where('name', 'marketing')
            ->where('guard_name', 'web')
            ->first();

        // Role marketing dibuat oleh seeder. Bila belum ada (database baru),
        // tidak ada yang dimigrasi dan tidak ada yang perlu dilakukan.
        if (! $role) {
            return;
        }

        $morphKey = config('permission.column_names.model_morph_key', 'model_id');

        $userIds = DB::table('users')
            ->where('role', 'staff')
            ->whereNotIn('id', function ($query) use ($tables, $morphKey) {
                $query->select($morphKey)
                    ->from($tables['model_has_roles'])
                    ->where('model_type', 'App\\Models\\User');
            })
            ->pluck('id');

        foreach ($userIds as $userId) {
            DB::table($tables['model_has_roles'])->insertOrIgnore([
                'role_id' => $role->id,
                'model_type' => 'App\\Models\\User',
                $morphKey => $userId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Migrasi data tidak dibalik: mencabut role yang sudah diberikan
     * akan menghilangkan akses user tanpa sepengetahuan admin.
     */
    public function down(): void
    {
        //
    }
};
