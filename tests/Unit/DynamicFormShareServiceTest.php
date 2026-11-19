<?php

namespace Tests\Unit;

use App\Enums\DynamicFormStatus;
use App\Models\DynamicForm;
use App\Services\DynamicForm\DynamicFormShareService;
use chillerlan\QRCode\QRCode;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Tests\TestCase;

class DynamicFormShareServiceTest extends TestCase
{
    private DynamicFormShareService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DynamicFormShareService::class);
    }

    public function test_resolves_public_base_url_with_fallback_to_webhook_origin(): void
    {
        Config::set('web_frontend.public_url', 'https://rs-elisabeth.com/');
        Config::set('web_frontend.webhook_url', 'http://172.17.12.17:2301/api/internal/webhook/cache-invalidate');
        $this->assertSame('https://rs-elisabeth.com', $this->service->resolvePublicBaseUrl());

        Config::set('web_frontend.public_url', null);
        $this->assertSame('http://172.17.12.17:2301', $this->service->resolvePublicBaseUrl());

        Config::set('web_frontend.public_url', '   ');
        Config::set('web_frontend.webhook_url', 'https://web.rs-elisabeth.test/api/internal/webhook/cache-invalidate');
        $this->assertSame('https://web.rs-elisabeth.test', $this->service->resolvePublicBaseUrl());

        Config::set('web_frontend.public_url', 'not-a-valid-url');
        $this->assertSame('https://web.rs-elisabeth.test', $this->service->resolvePublicBaseUrl());

        Config::set('web_frontend.public_url', null);
        Config::set('web_frontend.webhook_url', null);
        $this->assertNull($this->service->resolvePublicBaseUrl());
    }

    public function test_builds_public_and_qr_urls_and_enforces_share_eligibility(): void
    {
        Config::set('web_frontend.public_url', 'http://rs-elisabeth-web.test');

        $draft = new DynamicForm([
            'title' => 'Survei Draf',
            'slug' => 'survei-draf',
            'status' => DynamicFormStatus::Draft,
            'published_version_id' => null,
        ]);
        $this->assertFalse($this->service->canShare($draft));

        $published = new DynamicForm([
            'title' => 'Survei Terbit',
            'slug' => 'survei-terbit',
            'status' => DynamicFormStatus::Published,
            'published_version_id' => 10,
        ]);
        $this->assertTrue($this->service->canShare($published));
        $this->assertSame('/formulir/survei-terbit', $this->service->relativeWebPath($published));
        $this->assertSame('http://rs-elisabeth-web.test/formulir/survei-terbit', $this->service->publicUrl($published));
        $this->assertSame('http://rs-elisabeth-web.test/formulir/survei-terbit?source=qr_barcode', $this->service->qrUrl($published));
        $this->assertSame('qr-survei-terbit.png', $this->service->downloadFilename($published, 'png'));
        $this->assertSame('qr-survei-terbit.svg', $this->service->downloadFilename($published, 'svg'));

        $closedPublished = new DynamicForm([
            'title' => 'Survei Ditutup',
            'slug' => 'survei-ditutup',
            'status' => DynamicFormStatus::Closed,
            'published_version_id' => 12,
        ]);
        $this->assertTrue($this->service->canShare($closedPublished));

        $closedNeverPublished = new DynamicForm([
            'title' => 'Survei Ditutup Tanpa Versi',
            'slug' => 'survei-tanpa-versi',
            'status' => DynamicFormStatus::Closed,
            'published_version_id' => null,
        ]);
        $this->assertFalse($this->service->canShare($closedNeverPublished));

        $softDeleted = new DynamicForm([
            'title' => 'Survei Terhapus',
            'slug' => 'survei-terhapus',
            'status' => DynamicFormStatus::Published,
            'published_version_id' => 15,
        ]);
        $softDeleted->deleted_at = now();
        $this->assertFalse($this->service->canShare($softDeleted));
    }

    public function test_renders_valid_high_resolution_png_and_svg_qr_without_deprecation_warnings(): void
    {
        $targetUrl = 'http://rs-elisabeth-web.test/formulir/survei-kepatuhan-username-dan-password?source=qr_barcode';
        $deprecations = [];

        set_error_handler(function (int $severity, string $message) use (&$deprecations): bool {
            if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
                $deprecations[] = $message;
            }

            return false;
        });

        try {
            $png = $this->service->renderQrPng($targetUrl);
            $svg = $this->service->renderQrSvg($targetUrl);
            $dataUri = $this->service->renderQrSvgDataUri($targetUrl);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations);
        $this->assertStringStartsWith("\x89PNG\r\n\x1A\n", $png);

        $image = imagecreatefromstring($png);
        $this->assertNotFalse($image);
        $width = imagesx($image);
        $height = imagesy($image);
        $this->assertSame($width, $height);
        $this->assertGreaterThanOrEqual(900, $width);
        $this->assertLessThanOrEqual(1150, $width);
        // Sudut quiet zone harus putih opaque (#FFFFFF).
        $this->assertSame(0xFFFFFF, imagecolorat($image, 0, 0));
        $this->assertSame(-1, imagecolortransparent($image));

        $decoded = (string) (new QRCode)->readFromBlob($png);
        $this->assertSame($targetUrl, $decoded);

        // Uji juga slug maksimal 255 karakter agar skala QR PNG tetap stabil (~1000px) dan terbaca akurat.
        $longTargetUrl = 'http://rs-elisabeth-web.test/formulir/'.str_repeat('a', 255).'?source=qr_barcode';
        $longPng = $this->service->renderQrPng($longTargetUrl);
        $longImage = imagecreatefromstring($longPng);
        $this->assertNotFalse($longImage);
        $this->assertGreaterThanOrEqual(900, imagesx($longImage));
        $this->assertLessThanOrEqual(1150, imagesx($longImage));
        $this->assertSame($longTargetUrl, (string) (new QRCode)->readFromBlob($longPng));

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $svg);
        $this->assertStringContainsString('<svg xmlns="http://www.w3.org/2000/svg"', $svg);
        $this->assertStringContainsString('viewBox="', $svg);
        $this->assertStringContainsString('fill="#000"', $svg);
        $this->assertStringContainsString('fill="#fff"', $svg);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $dataUri);
    }

    public function test_build_modal_data_separates_base_url_configuration_from_missing_slug(): void
    {
        Config::set('web_frontend.public_url', 'http://rs-elisabeth-web.test');

        $missingSlug = new DynamicForm([
            'title' => 'Tanpa Slug',
            'slug' => '',
            'status' => DynamicFormStatus::Published,
            'published_version_id' => 1,
            'is_public_listed' => false,
        ]);

        $data = $this->service->buildModalData($missingSlug);
        $this->assertTrue($data['isConfigured']);
        $this->assertFalse($data['hasSlug']);
        $this->assertNull($data['publicUrl']);
        $this->assertNull($data['qrUrl']);
        $this->assertTrue($data['availability']['is_accepting_responses']);

        $renderedMissingSlug = view('filament.resources.dynamic-forms.components.share-modal', $data)->render();
        $this->assertStringContainsString('Slug Formulir Belum Tersedia.', $renderedMissingSlug);
        $this->assertStringNotContainsString('URL Publik Web Belum Dikonfigurasi.', $renderedMissingSlug);

        Config::set('web_frontend.public_url', null);
        Config::set('web_frontend.webhook_url', null);
        $unconfigured = $this->service->buildModalData($missingSlug);
        $this->assertFalse($unconfigured['isConfigured']);
    }

    public function test_rejects_empty_qr_url(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->renderQrPng('   ');
    }
}
