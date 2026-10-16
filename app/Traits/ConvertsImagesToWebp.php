<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

/**
 * Trait ConvertsImagesToWebp
 *
 * Secara otomatis mengkonversi file gambar yang baru di-upload (JPG/PNG)
 * menjadi format WebP yang teroptimasi setelah data model disimpan.
 *
 * Cara pakai:
 * 1. Tambahkan `use ConvertsImagesToWebp;` pada Model Eloquent.
 * 2. Implementasikan method `getWebpFields()` yang me-return array berisi
 *    nama-nama kolom yang menyimpan path gambar.
 *    Contoh: return ['image_path']; atau return ['thumbnail'];
 *
 * Trait ini menangani dua hal sekaligus:
 * a) Konversi file fisik di storage dari .jpg/.png menjadi .webp
 * b) Update kolom path di database agar menunjuk ke file .webp yang baru
 *    (mencegah gambar broken di frontend)
 *
 * Kompatibel dengan disk S3/MinIO maupun disk lokal (public).
 * Disk yang digunakan dibaca otomatis dari konfigurasi FILESYSTEM_DISK di .env.
 *
 * @mixin Model
 *
 * @method static void saved(\Closure $callback)
 */
trait ConvertsImagesToWebp
{
    /**
     * Kualitas output WebP (0-100).
     * 82 adalah sweet spot antara kualitas visual & ukuran file.
     */
    protected int $webpQuality = 82;

    /**
     * Disk media tunggal (config filesystems.media_disk). Sama dengan disk yang
     * dipakai form upload Filament dan URL gambar di API, sehingga file yang
     * diunggah, dikonversi, dan ditampilkan selalu berada di disk yang sama.
     */
    protected function getStorageDisk(): string
    {
        return config('filesystems.media_disk', 's3');
    }

    /**
     * Daftarkan hook konversi WebP saat model diinisialisasi.
     */
    public static function bootConvertsImagesToWebp(): void
    {
        static::saved(function (self $model) {
            $model->convertFieldsToWebp();
        });
    }

    /**
     * Kembalikan array nama kolom yang menyimpan path gambar.
     * Wajib diimplementasikan di setiap Model yang menggunakan Trait ini.
     *
     * @return array<int, string>
     */
    abstract protected function getWebpFields(): array;

    /**
     * Proses konversi untuk seluruh kolom gambar yang didaftarkan.
     */
    protected function convertFieldsToWebp(): void
    {
        $disk = $this->getStorageDisk();
        $needsUpdate = false;
        $updates = [];
        $originals = [];

        foreach ($this->getWebpFields() as $field) {
            $currentPath = $this->getRawOriginal($field) ?? $this->getAttribute($field);

            if (empty($currentPath)) {
                continue;
            }

            // Lewati jika sudah berformat .webp
            if (str_ends_with(strtolower($currentPath), '.webp')) {
                continue;
            }

            // Lewati jika file tidak ditemukan di storage (lokal maupun S3)
            if (! Storage::disk($disk)->exists($currentPath)) {
                Log::debug('ConvertsImagesToWebp: File tidak ditemukan di storage, dilewati.', [
                    'model' => static::class,
                    'disk' => $disk,
                    'path' => $currentPath,
                ]);

                continue;
            }

            $webpPath = $this->convertToWebp($disk, $currentPath);

            // Konversi gagal (null) atau tidak menghasilkan path baru: file asli
            // dibiarkan apa adanya dan kolom database tidak diubah.
            if ($webpPath && $webpPath !== $currentPath) {
                $updates[$field] = $webpPath;
                $originals[$field] = $currentPath;
                $needsUpdate = true;
            }
        }

        if (! $needsUpdate) {
            return;
        }

        // URUTAN AMAN: file .webp sudah tertulis -> update database -> BARU hapus
        // file asli. Jika update database gagal, file asli tetap ada dan .webp
        // yang baru dibuat dibersihkan, sehingga tidak ada record yang menunjuk
        // ke file yang hilang.
        try {
            $this->updateQuietly($updates);
        } catch (\Throwable $e) {
            foreach ($updates as $newPath) {
                Storage::disk($disk)->delete($newPath);
            }

            Log::error('ConvertsImagesToWebp: Gagal memperbarui database, file asli dipertahankan.', [
                'model' => static::class,
                'disk' => $disk,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        foreach ($originals as $oldPath) {
            Storage::disk($disk)->delete($oldPath);
        }
    }

    /**
     * Lakukan konversi file gambar ke format WebP menggunakan Intervention Image v4.
     *
     * Alur kerja (kompatibel dengan S3/MinIO dan disk lokal):
     * 1. Unduh konten binary file dari storage ke memori (RAM).
     * 2. Decode gambar dari binary menggunakan Intervention Image.
     * 3. Encode ke format WebP di memori.
     * 4. Upload hasil WebP langsung ke storage tanpa menyentuh filesystem server.
     * 5. Return path baru file WebP.
     *
     * @param  string  $disk  Nama disk yang digunakan (misal: 's3', 'public').
     * @param  string  $storagePath  Path relatif file di storage (misal: "news/abc.jpg").
     * @return string|null Path relatif file WebP yang baru, atau null jika gagal.
     */
    protected function convertToWebp(string $disk, string $storagePath): ?string
    {
        try {
            // 1. Unduh konten binary file dari storage (S3/MinIO/lokal) ke memori
            $fileContent = Storage::disk($disk)->get($storagePath);

            if (empty($fileContent)) {
                return null;
            }

            // 2. Decode gambar dari binary string
            $manager = new ImageManager(new Driver);
            $image = $manager->decode($fileContent);

            // 3. Tentukan path baru dengan ekstensi .webp
            $webpStoragePath = (string) preg_replace('/\.(jpe?g|png|gif|bmp)$/i', '.webp', $storagePath);

            // 4. Encode ke format WebP di memori menggunakan Intervention v4
            $encoded = $image->encode(new WebpEncoder($this->webpQuality));

            // 5. Upload hasil konversi WebP ke storage (S3/MinIO/lokal)
            //    Catatan: Visibilitas/akses publik diatur di level Bucket Policy
            //    pada dashboard MinIO, bukan di sini (MinIO tidak mendukung per-object ACL).
            Storage::disk($disk)->put(
                $webpStoragePath,
                (string) $encoded,
                ['ContentType' => 'image/webp']
            );

            return $webpStoragePath;

        } catch (\Throwable $e) {
            // Log error tanpa menghentikan proses simpan data utama
            Log::warning('ConvertsImagesToWebp: Gagal mengkonversi gambar.', [
                'model' => static::class,
                'disk' => $disk,
                'path' => $storagePath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
