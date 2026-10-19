<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\RecoveryCodeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Membuat ulang 10 kode pemulihan sekali pakai untuk akun super_admin (MVP-1.5).
 *
 * Seluruh kode pemulihan lama otomatis dihapus dan dibatalkan.
 */
class RegenerateSuperAdminRecoveryCodesCommand extends Command
{
    public const CONFIRM_QUESTION = 'Buat ulang 10 kode pemulihan baru dan batalkan seluruh kode pemulihan lama?';

    protected $signature = 'app:super-admin:recovery-codes:regenerate
                            {--force : Lewati konfirmasi interaktif}';

    protected $description = 'Buat ulang 10 kode pemulihan sekali pakai untuk super_admin dan batalkan kode lama.';

    public function handle(RecoveryCodeService $recoveryCodes): int
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
            $this->error('Akun super_admin berstatus nonaktif.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(self::CONFIRM_QUESTION)) {
            $this->info('Pembuatan ulang kode pemulihan dibatalkan.');

            return self::FAILURE;
        }

        $codes = DB::transaction(function () use ($user, $recoveryCodes): array {
            $generated = $recoveryCodes->generateFor($user);

            AuditLog::record('super_admin.recovery_codes_regenerated', $user, [
                'source' => 'cli',
                'host' => gethostname() ?: 'unknown',
            ]);

            return $generated;
        });

        $this->newLine();
        $this->info('Kode pemulihan darurat super_admin berhasil dibuat ulang.');
        $this->line("Email         : {$user->email}");
        foreach ($codes as $index => $code) {
            $number = $index + 1;
            $this->line("RECOVERY_CODE_{$number}={$code}");
        }
        $this->newLine();
        $this->warn('Kode pemulihan baru hanya ditampilkan SEKALI dan seluruh kode lama telah dibatalkan.');

        return self::SUCCESS;
    }
}
