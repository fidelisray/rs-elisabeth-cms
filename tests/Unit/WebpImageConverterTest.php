<?php

namespace Tests\Unit;

use App\Services\Media\WebpImageConverter;
use finfo;
use Tests\TestCase;

class WebpImageConverterTest extends TestCase
{
    private WebpImageConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new WebpImageConverter;
    }

    private function makePngBinary(int $width = 32, int $height = 32): string
    {
        $im = imagecreatetruecolor($width, $height);
        imagefill($im, 0, 0, imagecolorallocate($im, 25, 130, 196));
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    private function makeJpegBinary(int $width = 32, int $height = 32): string
    {
        $im = imagecreatetruecolor($width, $height);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 190, 11));
        ob_start();
        imagejpeg($im, null, 90);

        return (string) ob_get_clean();
    }

    public function test_recognizes_convertible_extensions_and_mimes_and_replaces_extensions_safely(): void
    {
        $this->assertTrue($this->converter->isConvertibleExtension('jpg'));
        $this->assertTrue($this->converter->isConvertibleExtension('.JPEG'));
        $this->assertTrue($this->converter->isConvertibleExtension('png'));
        $this->assertFalse($this->converter->isConvertibleExtension('webp'));
        $this->assertFalse($this->converter->isConvertibleExtension('pdf'));

        $this->assertTrue($this->converter->isConvertibleMime('image/jpeg'));
        $this->assertTrue($this->converter->isConvertibleMime('IMAGE/PNG'));
        $this->assertFalse($this->converter->isConvertibleMime('image/webp'));
        $this->assertFalse($this->converter->isConvertibleMime('application/pdf'));

        $this->assertSame('news/banner.webp', $this->converter->replaceExtensionWithWebp('news/banner.JPG'));
        $this->assertSame('bukti.login.webp', $this->converter->replaceExtensionWithWebp('bukti.login.png'));
        $this->assertNull($this->converter->replaceExtensionWithWebp('news/sudah.webp'));
        $this->assertNull($this->converter->replaceExtensionWithWebp('dokumen/panduan.pdf'));
        $this->assertNull($this->converter->replaceExtensionWithWebp('tanpa_ekstensi'));
    }

    public function test_encodes_valid_png_and_jpeg_binaries_and_files_to_valid_webp(): void
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);

        $pngWebp = $this->converter->encodeToWebp($this->makePngBinary(48, 36));
        $this->assertNotNull($pngWebp);
        $this->assertSame('image/webp', $finfo->buffer($pngWebp));

        $jpgWebp = $this->converter->encodeToWebp($this->makeJpegBinary(60, 40));
        $this->assertNotNull($jpgWebp);
        $this->assertSame('image/webp', $finfo->buffer($jpgWebp));

        $tmpFile = tempnam(sys_get_temp_dir(), 'rse_webp_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, $this->makePngBinary(20, 20));

        try {
            $fileWebp = $this->converter->encodeFileToWebp($tmpFile);
            $this->assertNotNull($fileWebp);
            $this->assertSame('image/webp', $finfo->buffer($fileWebp));
        } finally {
            @unlink($tmpFile);
        }
    }

    public function test_returns_null_for_empty_corrupted_or_oversized_dimension_images(): void
    {
        $this->assertNull($this->converter->encodeToWebp(''));
        $this->assertNull($this->converter->encodeToWebp('bukan-konten-gambar'));
        $this->assertNull($this->converter->encodeFileToWebp('/path/tidak/ada.png'));

        // Simulasikan header PNG dengan dimensi 7000 x 10 (> MAX_DIMENSION 6000) tanpa mengalokasikan bitmap besar.
        $png = $this->makePngBinary(10, 10);
        // Header IHDR PNG: byte 16..19 = width (big-endian), byte 20..23 = height (big-endian)
        $oversizedHeaderPng = substr($png, 0, 16)
            .pack('N', WebpImageConverter::MAX_DIMENSION + 500)
            .pack('N', 100)
            .substr($png, 24);

        $this->assertNull($this->converter->encodeToWebp($oversizedHeaderPng));

        // Simulasikan header PNG dengan dimensi 4500 x 4500 = 20.25 MP (> MAX_IMAGE_PIXELS 16 MP)
        $oversizedPixelsPng = substr($png, 0, 16)
            .pack('N', 4500)
            .pack('N', 4500)
            .substr($png, 24);

        $this->assertNull($this->converter->encodeToWebp($oversizedPixelsPng));
    }
}
