<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\RecoveryCodeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Membuat akun super_admin PERTAMA dengan password acak dan 10 kode pemulihan (K-6, K-18, K-26).
 *
 * - Hanya berhasil bila belum ada super_admin (aturan satu super_admin, K-3).
 * - Password acak dan 10 recovery code dicetak SATU KALI di terminal dan tidak disimpan dalam bentuk plaintext.
 * - Akun diwajibkan mengganti password saat login pertama (K-8).
 */
class InitSuperAdminCommand extends Command
{
    protected $signature = 'app:super-admin:init
                            {--email=admin@rs-elisabeth.com : Email akun super_admin}
                            {--name=Super Admin : Nama akun super_admin}';

    protected $description = 'Buat akun super_admin pertama dengan password acak dan 10 kode pemulihan.';

    public function handle(RecoveryCodeService $recoveryCodes): int
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
        $codes = [];

        $created = DB::transaction(function () use ($role, $email, $name, $password, $recoveryCodes, &$codes) {
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
                'must_change_password' => true,
                'is_active' => true,
            ]);

            $user->assignRole($role);
            $codes = $recoveryCodes->generateFor($user);
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
        $this->info('Kode Pemulihan Darurat (10 kode sekali pakai):');
        foreach ($codes as $index => $code) {
            $number = $index + 1;
            $this->line("RECOVERY_CODE_{$number}={$code}");
        }
        $this->newLine();
        $this->warn('Password awal dan kode pemulihan hanya ditampilkan SEKALI. Simpan di password manager sekarang.');
        $this->warn('Password ini wajib diganti saat login pertama.');

        return self::SUCCESS;
    }
}
