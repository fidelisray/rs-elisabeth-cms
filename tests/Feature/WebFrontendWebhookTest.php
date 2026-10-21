<?php

namespace Tests\Feature;

use App\Jobs\SendCacheInvalidationWebhookJob;
use App\Models\Article;
use App\Models\BannerPromotion;
use App\Models\FacilityService;
use App\Models\News;
use App\Models\Promotion;
use App\Models\RoomFacility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebFrontendWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('web_frontend.webhook_url', 'http://172.17.12.17:2301/api/internal/webhook/cache-invalidate');
        Config::set('web_frontend.cons_id', 'WEB-FRONTEND');
        Config::set('web_frontend.secret_key', 'test-secret-key-12345');
        Config::set('web_frontend.timeout', 5);
    }

    public function test_send_cache_invalidation_webhook_job_dispatches_http_request(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $job = new SendCacheInvalidationWebhookJob('news', 'content.updated', 'berita-populer');
        dispatch($job);

        Http::assertSent(function (Request $request) {
            $body = json_decode($request->body(), true);

            return $body['module'] === 'news'
                && $body['event'] === 'content.updated'
                && $body['slug'] === 'berita-populer';
        });
    }

    public function test_news_lifecycle_dispatches_webhook(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $news = News::create([
            'title' => ['id' => 'Judul Berita Baru', 'en' => 'New News Title'],
            'slug' => ['id' => 'judul-berita-baru', 'en' => 'new-news-title'],
            'content' => ['id' => 'Konten Berita Baru', 'en' => 'New news content'],
        ]);

        Http::assertSent(function (Request $request) {
            $body = json_decode($request->body(), true);

            return $body['module'] === 'news'
                && $body['slug'] === 'judul-berita-baru';
        });

        // Test delete
        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $news->delete();

        Http::assertSent(function (Request $request) {
            $body = json_decode($request->body(), true);

            return $body['module'] === 'news'
                && $body['event'] === 'content.deleted'
                && $body['slug'] === 'judul-berita-baru';
        });
    }

    public function test_article_lifecycle_dispatches_webhook(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $article = Article::create([
            'title' => ['id' => 'Artikel Kesehatan Jantung', 'en' => 'Heart Health Article'],
            'slug' => ['id' => 'artikel-kesehatan-jantung', 'en' => 'heart-health-article'],
            'content' => ['id' => 'Konten Artikel Kesehatan Jantung', 'en' => 'Heart health article content'],
        ]);

        Http::assertSent(function (Request $request) {
            $body = json_decode($request->body(), true);

            return $body['module'] === 'articles'
                && $body['slug'] === 'artikel-kesehatan-jantung';
        });

        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $article->delete();

        Http::assertSent(function (Request $request) {
            $body = json_decode($request->body(), true);

            return $body['module'] === 'articles'
                && $body['event'] === 'content.deleted'
                && $body['slug'] === 'artikel-kesehatan-jantung';
        });
    }

    public function test_promotion_lifecycle_dispatches_webhook(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $promo = Promotion::create([
            'title' => ['id' => 'Promo Medical Check Up', 'en' => 'Medical Check Up Promo'],
            'slug' => ['id' => 'promo-medical-check-up', 'en' => 'medical-check-up-promo'],
            'description' => ['id' => 'Deskripsi Promo MCU', 'en' => 'MCU promo description'],
        ]);

        Http::assertSent(function (Request $request) {
            $body = json_decode($request->body(), true);

            return $body['module'] === 'promotions'
                && $body['slug'] === 'promo-medical-check-up';
        });

        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $promo->delete();

        Http::assertSent(function (Request $request) {
            $body = json_decode($request->body(), true);

            return $body['module'] === 'promotions'
                && $body['event'] === 'content.deleted'
                && $body['slug'] === 'promo-medical-check-up';
        });
    }

    public function test_facility_service_lifecycle_dispatches_webhook(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $facility = FacilityService::create([
            'name' => ['id' => 'Layanan IGD 24 Jam', 'en' => 'ER 24 Hours Service'],
            'slug' => ['id' => 'layanan-igd-24-jam', 'en' => 'er-24-hours-service'],
            'description' => ['id' => 'Deskripsi Layanan IGD', 'en' => 'ER service description'],
        ]);

        Http::assertSent(function (Request $request) {
            $body = json_decode($request->body(), true);

            return $body['module'] === 'facilities'
                && $body['slug'] === 'layanan-igd-24-jam';
        });

        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $facility->delete();

        Http::assertSent(function (Request $request) {
            $body = json_decode($request->body(), true);

            return $body['module'] === 'facilities'
                && $body['event'] === 'content.deleted'
                && $body['slug'] === 'layanan-igd-24-jam';
        });
    }

    public function test_room_facility_lifecycle_dispatches_webhook(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $room = RoomFacility::create([
            'name' => ['id' => 'Kamar VIP Elisabeth', 'en' => 'Elisabeth VIP Room'],
            'slug' => ['id' => 'kamar-vip-elisabeth', 'en' => 'elisabeth-vip-room'],
            'description' => ['id' => 'Deskripsi Kamar VIP', 'en' => 'VIP room description'], 'tagline' => ['id' => 'Tag VIP', 'en' => 'VIP tag'],
        ]);

        Http::assertSent(function (Request $request) {
            $body = json_decode($request->body(), true);

            return $body['module'] === 'room_facilities'
                && $body['slug'] === 'kamar-vip-elisabeth';
        });

        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $room->delete();

        Http::assertSent(function (Request $request) {
            $body = json_decode($request->body(), true);

            return $body['module'] === 'room_facilities'
                && $body['event'] === 'content.deleted'
                && $body['slug'] === 'kamar-vip-elisabeth';
        });
    }

    public function test_banner_promotion_lifecycle_dispatches_webhook(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $banner = BannerPromotion::create([
            'title' => ['id' => 'Banner Hari Kesehatan', 'en' => 'Health Day Banner'],
            'slug' => ['id' => 'banner-hari-kesehatan', 'en' => 'health-day-banner'],
            'image_path' => 'banners/banner.webp',
        ]);

        Http::assertSent(function (Request $request) {
            $body = json_decode($request->body(), true);

            return $body['module'] === 'banner_promotions'
                && $body['slug'] === 'banner-hari-kesehatan';
        });

        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $banner->delete();

        Http::assertSent(function (Request $request) {
            $body = json_decode($request->body(), true);

            return $body['module'] === 'banner_promotions'
                && $body['event'] === 'content.deleted'
                && $body['slug'] === 'banner-hari-kesehatan';
        });
    }

    public function test_artisan_command_cache_invalidate(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        $this->artisan('webhook:cache-invalidate', [
            'module' => 'all',
            '--event' => 'content.updated',
        ])
            ->expectsOutputToContain('Berhasil')
            ->assertExitCode(0);

        Http::assertSent(function (Request $request) {
            $body = json_decode($request->body(), true);

            return $body['module'] === 'all'
                && $body['event'] === 'content.updated'
                && $body['slug'] === null;
        });
    }
}
