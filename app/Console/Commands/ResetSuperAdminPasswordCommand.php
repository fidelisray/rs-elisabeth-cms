<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mereset password akun super_admin melalui terminal server (K-5, K-23).
 *
 * - Hanya berjalan bila terdapat tepat satu akun super_admin aktif (K-3).
 * - Menghasilkan password acak 24 karakter yang dicetak satu kali di terminal.
 * - Mewajibkan ganti password pada login berikutnya dan menghapus seluruh sesi aktif.
 * - Mencatat kejadian ke audit_logs tanpa menyimpan password.
 */
class ResetSuperAdminPasswordCommand extends Command
{
    public const CONFIRM_QUESTION = 'Reset password akun super_admin dan cabut seluruh sesi aktif?';

    protected $signature = 'app:super-admin:reset-password
                            {--force : Lewati konfirmasi interaktif}';

    protected $description = 'Reset password akun super_admin dengan password acak baru dan cabut seluruh sesi aktif.';

    public function handle(): int
    {
        $role = Role::where('name', Role::SUPER_ADMIN)->where('guard_name', 'web')->first();

        if (! $role) {
            $this->error('Role super_admin belum ada di database.');

            return self::FAILURE;
        }

        $superAdmins = User::role(Role::SUPER_ADMIN)->get();

        if ($superAdmins->count() !== 1) {
            $this->error("Kondisi tidak normal: ditemukan {$superAdmins->count()} akun super_admin (seharusnya tepat 1).");

            return self::FAILURE;
        }

        /** @var User $user */
        $user = $superAdmins->first();

        if (! $user->is_active) {
            $this->error('Akun super_admin berstatus nonaktif. Periksa kondisi akun di database terlebih dahulu.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(self::CONFIRM_QUESTION)) {
            $this->info('Reset password super_admin dibatalkan.');

            return self::FAILURE;
        }

        $password = Str::password(24, symbols: false);

        DB::transaction(function () use ($user, $password): void {
            $user->update([
                'password' => $password,
                'must_change_password' => true,
            ]);

            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->delete();

            AuditLog::record('super_admin.password_reset', $user, [
                'source' => 'cli',
                'host' => gethostname() ?: 'unknown',
            ]);
        });

        $this->newLine();
        $this->info('Password akun super_admin berhasil direset.');
        $this->line("Email         : {$user->email}");
        $this->line("PASSWORD={$password}");
        $this->newLine();
        $this->warn('Password baru hanya ditampilkan SEKALI. Simpan di password manager sekarang.');
        $this->warn('Seluruh sesi aktif telah dicabut dan password ini wajib diganti saat login berikutnya.');

        return self::SUCCESS;
    }
}
