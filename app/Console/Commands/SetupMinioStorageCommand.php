<?php

namespace App\Console\Commands;

use Aws\S3\S3Client;
use Illuminate\Console\Command;

/**
 * Artisan command untuk mengonfigurasi MinIO Bucket Policy secara otomatis.
 *
 * Tugas utama:
 * 1. Membuat bucket jika belum ada.
 * 2. Mengatur Bucket Policy menjadi "public-read" agar file yang diupload
 *    bisa diakses secara publik oleh modul WEB tanpa autentikasi.
 *
 * Cara pakai:
 *   php artisan storage:setup-minio
 *
 * Cara integrasi di entrypoint.sh (opsional, jalankan saat container start):
 *   php artisan storage:setup-minio
 */
class SetupMinioStorageCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'storage:setup-minio';

    /**
     * The console command description.
     */
    protected $description = 'Membuat bucket MinIO (jika belum ada) dan mengatur Bucket Policy menjadi public-read.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $bucket   = config('filesystems.disks.s3.bucket');
        $endpoint = config('filesystems.disks.s3.endpoint');
        $region   = config('filesystems.disks.s3.region', 'us-east-1');
        $key      = config('filesystems.disks.s3.key');
        $secret   = config('filesystems.disks.s3.secret');

        // Validasi: pastikan semua konfigurasi tersedia sebelum memulai
        if (empty($bucket) || empty($endpoint) || empty($key) || empty($secret)) {
            $this->error('Konfigurasi MinIO tidak lengkap. Pastikan MINIO_* sudah diatur di .env.');

            return Command::FAILURE;
        }

        $this->info("Menghubungkan ke MinIO: {$endpoint}");
        $this->info("Bucket target: {$bucket}");

        // Buat S3Client secara langsung menggunakan AWS SDK untuk akses fitur-fitur
        // tingkat lanjut (seperti setBucketPolicy) yang tidak tersedia via Laravel Storage facade.
        $s3 = new S3Client([
            'version'                 => 'latest',
            'region'                  => $region,
            'endpoint'                => $endpoint,
            'use_path_style_endpoint' => true,
            'credentials'             => [
                'key'    => $key,
                'secret' => $secret,
            ],
        ]);

        // --- Langkah 1: Buat bucket jika belum ada ---
        try {
            if (! $s3->doesBucketExist($bucket)) {
                $this->warn("Bucket '{$bucket}' tidak ditemukan. Membuat bucket baru...");
                $s3->createBucket(['Bucket' => $bucket]);
                $this->info("✓ Bucket '{$bucket}' berhasil dibuat.");
            } else {
                $this->info("✓ Bucket '{$bucket}' sudah ada.");
            }
        } catch (\Throwable $e) {
            $this->error("Gagal memeriksa/membuat bucket: " . $e->getMessage());

            return Command::FAILURE;
        }

        // --- Langkah 2: Set Bucket Policy menjadi "public-read" ---
        // Policy ini mengizinkan SIAPA SAJA (internet publik) untuk melakukan
        // operasi "GetObject" (baca/unduh file) dari bucket ini.
        // Ini adalah standar keamanan yang tepat untuk hosting media publik.
        $publicReadPolicy = json_encode([
            'Version'   => '2012-10-17',
            'Statement' => [
                [
                    'Sid'       => 'PublicReadGetObject',
                    'Effect'    => 'Allow',
                    'Principal' => '*',
                    'Action'    => 's3:GetObject',
                    'Resource'  => "arn:aws:s3:::{$bucket}/*",
                ],
            ],
        ]);

        try {
            $s3->putBucketPolicy([
                'Bucket' => $bucket,
                'Policy' => $publicReadPolicy,
            ]);

            $this->info("✓ Bucket Policy berhasil diatur menjadi public-read.");
            $this->newLine();
            $this->info('==========================================================');
            $this->info(' Setup MinIO selesai! Semua file yang diupload ke bucket');
            $this->info(" '{$bucket}' kini dapat diakses secara publik.");
            $this->info('==========================================================');

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("Gagal mengatur Bucket Policy: " . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
