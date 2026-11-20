<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Log;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Service terpusat untuk konversi gambar raster (JPG/PNG/GIF/BMP) ke format WebP.
 *
 * Digunakan bersama oleh:
 * - Trait ConvertsImagesToWebp (modul konten publik: News, Article, Promotion, dll.)
 * - DynamicFormUploadApiController (lampiran gambar responden Dynamic Questionnaire)
 */
class WebpImageConverter
{
    public const DEFAULT_QUALITY = 82;

    /**
     * Batas dimensi per sisi (6.000 px) dan total piksel (16 MP) untuk mencegah
     * decompression bomb / kehabisan memori GD pada kontainer PHP-FPM 256 MB.
     */
    public const MAX_DIMENSION = 6000;

    public const MAX_IMAGE_PIXELS = 16_000_000;

    /**
     * @var list<string>
     */
    public const CONVERTIBLE_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'bmp',
    ];

    /**
     * @var list<string>
     */
    public const CONVERTIBLE_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/bmp',
        'image/x-ms-bmp',
    ];

    public function isConvertibleExtension(string $extension): bool
    {
        return in_array(strtolower(trim($extension, ". \t\n\r\0\x0B")), self::CONVERTIBLE_EXTENSIONS, true);
    }

    public function isConvertibleMime(string $mimeType): bool
    {
        return in_array(strtolower(trim($mimeType)), self::CONVERTIBLE_MIMES, true);
    }

    /**
     * Ganti ekstensi akhir berkas gambar yang didukung menjadi .webp.
     * Mengembalikan null apabila path/nama berkas tidak berakhiran ekstensi yang dapat dikonversi.
     */
    public function replaceExtensionWithWebp(string $pathOrFilename): ?string
    {
        $trimmed = trim($pathOrFilename);
        if ($trimmed === '' || preg_match('/\.(jpe?g|png|gif|bmp)$/i', $trimmed) !== 1) {
            return null;
        }

        $replaced = preg_replace('/\.(jpe?g|png|gif|bmp)$/i', '.webp', $trimmed);

        return is_string($replaced) && $replaced !== '' && $replaced !== $trimmed ? $replaced : null;
    }

    /**
     * Konversi berkas gambar lokal (misal temporary upload PHP) ke binary WebP.
     * Memeriksa dimensi via getimagesize() sebelum membaca seluruh isi file ke memori.
     *
     * @param  array<string, mixed>  $logContext
     */
    public function encodeFileToWebp(
        string $filePath,
        int $quality = self::DEFAULT_QUALITY,
        array $logContext = [],
    ): ?string {
        if ($filePath === '' || ! is_file($filePath) || ! is_readable($filePath)) {
            return null;
        }

        $imageInfo = @getimagesize($filePath);
        if (! is_array($imageInfo)) {
            Log::warning('WebpImageConverter: Gagal membaca header dimensi berkas gambar.', $logContext + [
                'file_path' => $filePath,
            ]);

            return null;
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        if (! $this->dimensionsWithinBudget($width, $height, $logContext)) {
            return null;
        }

        $binary = @file_get_contents($filePath);
        if (! is_string($binary) || $binary === '') {
            return null;
        }

        return $this->encodeToWebp($binary, $quality, $logContext, skipDimensionCheck: true);
    }

    /**
     * Konversi konten binary gambar ke binary WebP di memori menggunakan Intervention Image v4 (GD).
     *
     * @param  array<string, mixed>  $logContext
     */
    public function encodeToWebp(
        string $binaryContent,
        int $quality = self::DEFAULT_QUALITY,
        array $logContext = [],
        bool $skipDimensionCheck = false,
    ): ?string {
        if ($binaryContent === '' || ! function_exists('imagewebp')) {
            return null;
        }

        try {
            if (! $skipDimensionCheck) {
                $imageInfo = @getimagesizefromstring($binaryContent);
                if (! is_array($imageInfo)) {
                    Log::warning('WebpImageConverter: Konten binary bukan gambar raster yang valid.', $logContext);

                    return null;
                }

                $width = (int) ($imageInfo[0] ?? 0);
                $height = (int) ($imageInfo[1] ?? 0);
                if (! $this->dimensionsWithinBudget($width, $height, $logContext)) {
                    return null;
                }
            }

            $clampedQuality = max(1, min(100, $quality));
            $manager = new ImageManager(new Driver);
            $image = $manager->decode($binaryContent);

            // Koreksi rotasi berdasarkan metadata EXIF (penting untuk foto kamera smartphone)
            $image->orient();

            $encoded = (string) $image->encode(new WebpEncoder($clampedQuality));
            unset($image);

            return $encoded !== '' ? $encoded : null;
        } catch (Throwable $e) {
            Log::warning('WebpImageConverter: Gagal mengkonversi gambar ke WebP.', $logContext + [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $logContext
     */
    private function dimensionsWithinBudget(int $width, int $height, array $logContext = []): bool
    {
        if ($width <= 0 || $height <= 0) {
            return false;
        }

        $totalPixels = $width * $height;
        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION || $totalPixels > self::MAX_IMAGE_PIXELS) {
            Log::warning('WebpImageConverter: Dimensi gambar melebihi batas aman memori GD, konversi dilewati.', $logContext + [
                'width' => $width,
                'height' => $height,
                'pixels' => $totalPixels,
                'max_dimension' => self::MAX_DIMENSION,
                'max_pixels' => self::MAX_IMAGE_PIXELS,
            ]);

            return false;
        }

        return true;
    }
}
