<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Mengembalikan database ke kondisi "fresh" saat fase testing/pre-production.
 *
 * MENGHAPUS SELURUH TABEL DAN DATA, lalu menjalankan ulang seluruh migration dan
 * seeder dari nol, dan membuat super_admin baru dengan password acak.
 *
 * Pengaman berlapis (sengaja tidak memakai APP_ENV karena docker-compose
 * memaksa APP_ENV=production di semua server, termasuk pre-production):
 * 1. Wajib ALLOW_DESTRUCTIVE_RESET=true di .env server tersebut. Server go-live
 *    tidak boleh memiliki nilai ini.
 * 2. Konfirmasi interaktif, kecuali memakai --force.
 *
 * Cara pakai (server Docker):
 *   docker compose run --rm cms_app php artisan app:preprod:reset
 *
 * Yang TIDAK dibersihkan: file gambar di MinIO/S3. File lama menjadi yatim
 * (tidak lagi dirujuk database) dan perlu dihapus manual bila ingin bersih.
 */
class PreprodResetCommand extends Command
{
    public const CONFIRM_QUESTION = 'Seluruh tabel dan data akan DIHAPUS lalu dibuat ulang. Lanjutkan?';

    protected $signature = 'app:preprod:reset
                            {--force : Lewati konfirmasi interaktif}
                            {--skip-init : Jangan membuat super_admin setelah reset}
                            {--email=admin@rs-elisabeth.com : Email akun super_admin yang dibuat}
                            {--name=Super Admin : Nama akun super_admin yang dibuat}';

    protected $description = 'HAPUS SEMUA DATA lalu migrate:fresh --seed dan buat super_admin baru (hanya pre-production).';

    public function handle(): int
    {
        if (! config('app.allow_destructive_reset')) {
            $this->error('Ditolak: reset database tidak diizinkan di server ini.');
            $this->line('Set ALLOW_DESTRUCTIVE_RESET=true di .env HANYA pada server pre-production/testing, lalu restart container.');
            $this->line('Server go-live tidak boleh mengaktifkannya.');

            return self::FAILURE;
        }

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        $this->warn("Koneksi: {$connection} | Database: {$database}");

        if (! $this->option('force') && ! $this->confirm(self::CONFIRM_QUESTION, false)) {
            $this->info('Dibatalkan. Tidak ada perubahan.');

            return self::FAILURE;
        }

        if ($this->call('migrate:fresh', ['--seed' => true, '--force' => true]) !== self::SUCCESS) {
            $this->error('migrate:fresh --seed gagal. Super admin tidak dibuat.');

            return self::FAILURE;
        }

        // Cache (termasuk cache permission Spatie dan versi cache API) harus bersih
        // agar tidak ada sisa dari database sebelumnya.
        $this->call('cache:clear');

        if ($this->option('skip-init')) {
            $this->warn('Dilewati --skip-init: belum ada super_admin. Jalankan: php artisan app:super-admin:init');
        } elseif ($this->call('app:super-admin:init', [
            '--email' => (string) $this->option('email'),
            '--name' => (string) $this->option('name'),
        ]) !== self::SUCCESS) {
            $this->error('Database sudah direset, tetapi pembuatan super_admin gagal.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Reset selesai. Database kini fresh.');
        $this->line('Catatan: file gambar lama di MinIO/S3 tidak ikut terhapus.');

        return self::SUCCESS;
    }
}
