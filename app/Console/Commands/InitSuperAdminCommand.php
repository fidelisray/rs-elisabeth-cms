<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Membuat akun super_admin PERTAMA dengan password acak 24 karakter (K-6, K-18).
 *
 * - Hanya berhasil bila belum ada super_admin (aturan satu super_admin, K-3).
 * - Password acak dicetak SATU KALI di terminal dan tidak disimpan dalam bentuk plaintext.
 * - Akun diwajibkan mengganti password saat login pertama (K-8).
 * - Pemulihan darurat akun super_admin dilakukan eksklusif melalui CLI server (app:super-admin:reset-password).
 */
class InitSuperAdminCommand extends Command
{
    protected $signature = 'app:super-admin:init
                            {--email=admin@rs-elisabeth.com : Email akun super_admin}
                            {--name=Super Admin : Nama akun super_admin}';

    protected $description = 'Buat akun super_admin pertama dengan password acak 24 karakter.';

    public function handle(): int
    {
        $email = User::normalizeEmail((string) $this->option('email'));
        $name = (string) $this->option('name');

        $validator = Validator::make(['email' => $email, 'name' => $name], [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $permissions = Permission::where('guard_name', 'web')->get();

        if ($permissions->isEmpty()) {
            $this->error('Permission belum tersedia. Jalankan terlebih dahulu: php artisan db:seed --force');

            return self::FAILURE;
        }

        $password = Str::password(24, symbols: false);

        $created = DB::transaction(function () use ($permissions, $email, $name, $password) {
            $existingRole = Role::where('name', Role::SUPER_ADMIN)
                ->where('guard_name', 'web')
                ->lockForUpdate()
                ->first();

            if ($existingRole && User::role(Role::SUPER_ADMIN)->exists()) {
                return false;
            }

            if (User::where('email', $email)->exists()) {
                return null;
            }

            $role = $existingRole ?? Role::findOrCreate(Role::SUPER_ADMIN, 'web');
            $role->givePermissionTo($permissions);

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'must_change_password' => true,
                'is_active' => true,
            ]);

            $user->assignRole($role);
            AuditLog::record('super_admin.created', $user, ['source' => 'app:super-admin:init']);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return true;
        });

        if ($created === false) {
            $this->error('Akun super_admin sudah ada. Tidak ada perubahan yang dilakukan.');

            return self::FAILURE;
        }

        if ($created === null) {
            $this->error("Email {$email} sudah dipakai akun lain. Gunakan --email yang berbeda.");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Akun super_admin berhasil dibuat.');
        $this->line("Email         : {$email}");
        $this->line("PASSWORD      : {$password}");
        $this->newLine();
        $this->warn('Password awal hanya ditampilkan SEKALI. Simpan di password manager sekarang.');
        $this->warn('Password ini wajib diganti saat login pertama.');
        $this->line('Pemulihan darurat Super Admin hanya melalui konsol server: php artisan app:super-admin:reset-password');

        return self::SUCCESS;
    }
}
