<?php

namespace App\Console\Commands;

use Aws\S3\S3Client;
use Illuminate\Console\Command;

/**
 * Artisan command untuk mengonfigurasi MinIO Bucket Policy secara otomatis.
 *
 * Tugas utama:
 * 1. Membuat bucket jika belum ada.
 * 2. Mengatur Bucket Policy "public-read" khusus pada daftar prefix folder
 *    modul konten publik (PUBLIC_MEDIA_PREFIXES) agar aset publik dapat
 *    diakses oleh modul WEB tanpa autentikasi, sementara folder privat
 *    seperti "dynamic-forms/*" tetap tertutup (403 untuk akses anonim).
 *
 * Cara pakai:
 *   php artisan storage:setup-minio
 */
class SetupMinioStorageCommand extends Command
{
    /**
     * Daftar prefix direktori media publik yang diizinkan public-read pada bucket MinIO.
     * Direktori di luar daftar ini (misal: dynamic-forms/*) otomatis bersifat privat.
     *
     * @var list<string>
     */
    public const PUBLIC_MEDIA_PREFIXES = [
        'articles',
        'banners',
        'facility_services',
        'news',
        'online-libraries',
        'promotions',
        'room_facilities',
    ];

    /**
     * The name and signature of the console command.
     */
    protected $signature = 'storage:setup-minio';

    /**
     * The console command description.
     */
    protected $description = 'Membuat bucket MinIO (jika belum ada) dan mengatur Bucket Policy public-read khusus prefix media publik.';

    /**
     * Susun dokumen Bucket Policy S3/MinIO yang membatasi akses baca anonim
     * hanya pada prefix folder modul konten publik.
     *
     * @return array<string, mixed>
     */
    public static function buildPublicReadPolicy(string $bucket): array
    {
        $trimmedBucket = trim($bucket);
        $resources = array_map(
            static fn (string $prefix): string => "arn:aws:s3:::{$trimmedBucket}/{$prefix}/*",
            self::PUBLIC_MEDIA_PREFIXES
        );

        return [
            'Version' => '2012-10-17',
            'Statement' => [
                [
                    'Sid' => 'PublicReadGetObjectForPublicModulesOnly',
                    'Effect' => 'Allow',
                    'Principal' => '*',
                    'Action' => 's3:GetObject',
                    'Resource' => $resources,
                ],
            ],
        ];
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $bucket = config('filesystems.disks.s3.bucket');
        $endpoint = config('filesystems.disks.s3.endpoint');
        $region = config('filesystems.disks.s3.region', 'us-east-1');
        $key = config('filesystems.disks.s3.key');
        $secret = config('filesystems.disks.s3.secret');
        $usePathStyle = (bool) config('filesystems.disks.s3.use_path_style_endpoint', true);

        // Validasi: pastikan semua konfigurasi tersedia sebelum memulai
        if (empty($bucket) || empty($endpoint) || empty($key) || empty($secret)) {
            $this->error('Konfigurasi MinIO tidak lengkap. Pastikan MINIO_* sudah diatur di .env.');

            return Command::FAILURE;
        }

        $this->info("Menghubungkan ke MinIO: {$endpoint}");
        $this->info("Bucket target: {$bucket}");

        // Buat S3Client secara langsung menggunakan AWS SDK untuk akses fitur-fitur
        // tingkat lanjut (seperti putBucketPolicy) yang tidak tersedia via Laravel Storage facade.
        $s3 = new S3Client([
            'version' => 'latest',
            'region' => $region,
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => $usePathStyle,
            'retries' => 1,
            'http' => [
                'connect_timeout' => 5,
                'timeout' => 10,
            ],
            'credentials' => [
                'key' => $key,
                'secret' => $secret,
            ],
        ]);

        // Langkah 1: Buat bucket jika belum ada
        try {
            if (! $s3->doesBucketExist($bucket)) {
                $this->warn("Bucket '{$bucket}' tidak ditemukan. Membuat bucket baru...");
                $s3->createBucket(['Bucket' => $bucket]);
                $this->info("✓ Bucket '{$bucket}' berhasil dibuat.");
            } else {
                $this->info("✓ Bucket '{$bucket}' sudah ada.");
            }
        } catch (\Throwable $e) {
            $this->error('Gagal memeriksa/membuat bucket: '.$e->getMessage());

            return Command::FAILURE;
        }

        // Langkah 2: Set Bucket Policy public-read khusus prefix modul konten publik
        $policyArray = self::buildPublicReadPolicy((string) $bucket);
        $publicReadPolicy = json_encode($policyArray, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        try {
            $s3->putBucketPolicy([
                'Bucket' => $bucket,
                'Policy' => $publicReadPolicy,
            ]);

            $this->info('✓ Bucket Policy berhasil diatur ke mode prefix-scoped public-read.');
            $this->info('  - Prefix publik (200 OK): '.implode(', ', array_map(static fn (string $p): string => "{$p}/*", self::PUBLIC_MEDIA_PREFIXES)));
            $this->info('  - Prefix privat (403 Anonim): dynamic-forms/* dan seluruh path di luar whitelist');
            $this->newLine();
            $this->info('==========================================================');
            $this->info(" Setup MinIO selesai untuk bucket '{$bucket}'.");
            $this->info('==========================================================');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Gagal mengatur Bucket Policy: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
