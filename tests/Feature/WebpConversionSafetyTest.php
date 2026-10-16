<?php

namespace Tests\Feature;

use App\Http\Resources\Api\Cms\BaseCmsResource;
use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * B3/B4: konversi WebP tidak boleh menghapus file asli sebelum database
 * berhasil diperbarui, dan semua kode harus memakai satu disk media.
 */
class WebpConversionSafetyTest extends TestCase
{
    use RefreshDatabase;

    private string $disk = 'media-test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.media_disk' => $this->disk]);
        Storage::fake($this->disk);
    }

    private function png(): string
    {
        $im = imagecreatetruecolor(40, 40);
        imagefill($im, 0, 0, imagecolorallocate($im, 200, 30, 30));
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    private function newsWithImage(string $path, ?string $content = null): News
    {
        Storage::disk($this->disk)->put($path, $content ?? $this->png());

        // Simpan tanpa memicu konversi, lalu uji konversi secara eksplisit.
        return News::withoutEvents(fn () => News::create([
            'title' => 'Berita Uji',
            'slug' => 'berita-uji-'.uniqid(),
            'content' => 'isi',
            'image_path' => $path,
        ]));
    }

    private function convert(News $news): void
    {
        (function () {
            $this->convertFieldsToWebp();
        })->call($news);
    }

    public function test_successful_conversion_swaps_file_and_updates_database(): void
    {
        $news = $this->newsWithImage('news/foto.png');

        $this->convert($news);

        Storage::disk($this->disk)->assertExists('news/foto.webp');
        Storage::disk($this->disk)->assertMissing('news/foto.png');
        $this->assertSame('news/foto.webp', $news->fresh()->image_path);
    }

    public function test_original_file_survives_when_database_update_fails(): void
    {
        $news = $this->newsWithImage('news/gagal.png');

        // Paksa update database gagal setelah file .webp berhasil dibuat.
        News::withoutEvents(function () use ($news) {
            $broken = new class extends News
            {
                protected $table = 'news';

                public function updateQuietly(array $attributes = [], array $options = [])
                {
                    throw new \RuntimeException('Simulasi kegagalan database.');
                }
            };
            $broken->setRawAttributes($news->getAttributes(), true);
            $broken->exists = true;

            try {
                (function () {
                    $this->convertFieldsToWebp();
                })->call($broken);
            } catch (\Throwable) {
                // Kegagalan tidak boleh menyebabkan file asli hilang.
            }
        });

        Storage::disk($this->disk)->assertExists('news/gagal.png');
        Storage::disk($this->disk)->assertMissing('news/gagal.webp');
        $this->assertSame('news/gagal.png', $news->fresh()->image_path);
    }

    public function test_original_file_survives_when_conversion_fails(): void
    {
        $news = $this->newsWithImage('news/rusak.png', 'bukan gambar sama sekali');

        $this->convert($news);

        Storage::disk($this->disk)->assertExists('news/rusak.png');
        Storage::disk($this->disk)->assertMissing('news/rusak.webp');
        $this->assertSame('news/rusak.png', $news->fresh()->image_path);
    }

    public function test_conversion_uses_media_disk_even_when_default_disk_differs(): void
    {
        config(['filesystems.default' => 'local']);

        $news = $this->newsWithImage('news/disk.png');

        $this->convert($news);

        Storage::disk($this->disk)->assertExists('news/disk.webp');
        $this->assertSame('news/disk.webp', $news->fresh()->image_path);
    }

    public function test_api_image_url_uses_media_disk(): void
    {
        // Dua disk dengan URL dasar berbeda. media_disk harus menang atas default.
        config([
            'filesystems.disks.cdn-media' => ['driver' => 'local', 'root' => storage_path('framework/testing/a'), 'url' => 'https://media.example.test'],
            'filesystems.disks.cdn-default' => ['driver' => 'local', 'root' => storage_path('framework/testing/b'), 'url' => 'https://default.example.test'],
            'filesystems.media_disk' => 'cdn-media',
            'filesystems.default' => 'cdn-default',
        ]);

        $resource = new class(null) extends BaseCmsResource
        {
            public function url(?string $path): ?string
            {
                return $this->generateImageUrl($path);
            }
        };

        $this->assertSame('https://media.example.test/news/x.webp', $resource->url('news/x.webp'));
    }

    public function test_no_literal_s3_disk_remains_in_app_code(): void
    {
        $offenders = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            if (preg_match("/->disk\\(\\s*'s3'\\s*\\)/", $source)) {
                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders, 'Gunakan config(filesystems.media_disk), bukan literal s3.');
    }

    public function test_media_disk_defaults_to_s3_when_env_is_not_set(): void
    {
        $config = require config_path('filesystems.php');

        $this->assertArrayHasKey('media_disk', $config);
        $this->assertSame('s3', $config['media_disk']);
        $this->assertArrayHasKey('s3', $config['disks']);
    }
}
