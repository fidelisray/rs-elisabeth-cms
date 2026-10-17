<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Membuat akun super_admin PERTAMA dengan password acak (K-6, K-18).
 *
 * - Hanya berhasil bila belum ada super_admin (aturan satu super_admin, K-3).
 * - Password acak dicetak SATU KALI di terminal dan tidak disimpan di mana pun.
 * - Akun diwajibkan mengganti password saat login pertama (K-8).
 *
 * Command ini bukan jalur pemulihan. Pemulihan akun yang sudah ada dibahas
 * di Next Phase (N-1).
 */
class InitSuperAdminCommand extends Command
{
    protected $signature = 'app:super-admin:init
                            {--email=admin@rs-elisabeth.com : Email akun super_admin}
                            {--name=Super Admin : Nama akun super_admin}';

    protected $description = 'Buat akun super_admin pertama dengan password acak (hanya bila belum ada super_admin).';

    public function handle(): int
    {
        $email = (string) $this->option('email');
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

        $role = Role::where('name', 'super_admin')->where('guard_name', 'web')->first();

        if (! $role) {
            $this->error('Role super_admin belum ada. Jalankan terlebih dahulu: php artisan db:seed --force');

            return self::FAILURE;
        }

        $password = Str::password(24, symbols: false);

        $created = DB::transaction(function () use ($role, $email, $name, $password) {
            // Kunci baris role agar dua eksekusi bersamaan tidak membuat dua super_admin.
            Role::whereKey($role->getKey())->lockForUpdate()->first();

            if (User::role('super_admin')->exists()) {
                return false;
            }

            if (User::where('email', $email)->exists()) {
                return null;
            }

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => 'super_admin',
                'must_change_password' => true,
                'is_active' => true,
            ]);

            $user->assignRole($role);
            AuditLog::record('super_admin.created', $user, ['source' => 'app:super-admin:init']);

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
        $this->line("PASSWORD={$password}");
        $this->newLine();
        $this->warn('Password awal hanya ditampilkan SEKALI. Simpan di password manager sekarang.');
        $this->warn('Password ini wajib diganti saat login pertama.');

        return self::SUCCESS;
    }
}
