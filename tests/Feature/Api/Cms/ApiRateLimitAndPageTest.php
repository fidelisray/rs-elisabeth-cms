<?php

namespace Tests\Feature\Api\Cms;

use App\Models\Article;
use App\Models\News;
use App\Models\Promotion;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\GeneratesHmacHeaders;

/**
 * MVP-3.1: API dibatasi lajunya dan parameter page tidak boleh dipakai mentah
 * sebagai bagian key cache (kunci cache bisa dibuat tanpa batas).
 */
class ApiRateLimitAndPageTest extends TestCase
{
    use GeneratesHmacHeaders, RefreshDatabase;

    private function clearLimiters(): void
    {
        app(RateLimiter::class)->clear('cms-api:TESTING-APP');
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['cms_api.rate_limit_per_minute' => 5]);
        $this->clearLimiters();
    }

    // -------------------------------------------------------- rate limit

    public function test_requests_beyond_the_limit_return_429_with_json_envelope(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->withHmac()->getJson('/api/v1/cms/news')->assertOk();
        }

        $this->withHmac()->getJson('/api/v1/cms/news')
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status_code', 429);
    }

    public function test_429_response_includes_retry_after_header(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->withHmac()->getJson('/api/v1/cms/news');
        }

        $response = $this->withHmac()->getJson('/api/v1/cms/news')->assertStatus(429);

        $this->assertNotNull($response->headers->get('Retry-After'));
    }

    public function test_limit_is_shared_across_endpoints_for_the_same_client(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->withHmac()->getJson('/api/v1/cms/news')->assertOk();
        }
        for ($i = 0; $i < 2; $i++) {
            $this->withHmac()->getJson('/api/v1/cms/articles')->assertOk();
        }

        $this->withHmac()->getJson('/api/v1/cms/promotions')->assertStatus(429);
    }

    public function test_successful_responses_expose_rate_limit_headers(): void
    {
        $response = $this->withHmac()->getJson('/api/v1/cms/news')->assertOk();

        $this->assertNotNull($response->headers->get('X-RateLimit-Limit'));
        $this->assertNotNull($response->headers->get('X-RateLimit-Remaining'));
    }

    public function test_requests_without_hmac_do_not_consume_a_valid_clients_quota(): void
    {
        config(['cms_api.rate_limit_per_ip_per_minute' => 1000]);

        for ($i = 0; $i < 8; $i++) {
            $this->getJson('/api/v1/cms/news')->assertStatus(401);
        }

        $this->withHmac()->getJson('/api/v1/cms/news')->assertOk();
    }

    public function test_attacker_forging_signature_with_a_known_cons_id_cannot_lock_out_the_real_client(): void
    {
        // Cons-ID bukan rahasia. Penyerang hanya perlu mengetahuinya, lalu mengirim
        // signature palsu berulang kali. Kuota klien sah tidak boleh ikut habis.
        config(['cms_api.rate_limit_per_ip_per_minute' => 1000]);

        for ($i = 0; $i < 12; $i++) {
            $this->withHeaders([
                'X-Cons-ID' => 'TESTING-APP',
                'X-Timestamp' => (string) time(),
                'X-Signature' => 'signature-palsu',
                'Accept' => 'application/json',
            ])->getJson('/api/v1/cms/news')->assertStatus(401);
        }

        $this->withHmac()->getJson('/api/v1/cms/news')->assertOk();
    }

    public function test_forged_requests_are_still_throttled_per_ip(): void
    {
        config(['cms_api.rate_limit_per_ip_per_minute' => 4]);

        $statuses = [];
        for ($i = 0; $i < 7; $i++) {
            $statuses[] = $this->getJson('/api/v1/cms/news')->getStatusCode();
        }

        $this->assertContains(429, $statuses, 'Banjir request tanpa HMAC harus tetap dibatasi per IP.');
        $this->assertSame(401, $statuses[0]);
    }

    public function test_limit_is_configurable_and_has_a_sane_default(): void
    {
        $config = require config_path('cms_api.php');

        $this->assertArrayHasKey('rate_limit_per_minute', $config);
        $this->assertSame(60, $config['rate_limit_per_minute']);
    }

    // ---------------------------------------------------------- page

    private function seedNews(int $count): void
    {
        News::withoutEvents(function () use ($count) {
            foreach (range(1, $count) as $i) {
                News::create([
                    'title' => ['id' => "Berita {$i}", 'en' => "News {$i}"],
                    'slug' => ['id' => "berita-{$i}", 'en' => "news-{$i}"],
                    'content' => ['id' => 'isi', 'en' => 'content'],
                    'is_published' => true,
                ]);
            }
        });
    }

    private function pageKeys(): array
    {
        $store = Cache::getStore();
        $storage = (new \ReflectionProperty($store, 'storage'))->getValue($store);

        return collect(array_keys($storage))->filter(fn ($k) => str_contains($k, 'rs_web_cms_api_news_v') && str_contains($k, '_page_'))->values()->all();
    }

    public function test_invalid_page_values_fall_back_to_page_one(): void
    {
        config(['cms_api.rate_limit_per_minute' => 1000]);
        $this->seedNews(14);

        foreach (['abc', '-1', '0', '1.5', ''] as $bad) {
            $response = $this->withHmac()->getJson('/api/v1/cms/news?page='.urlencode($bad))->assertOk();

            $this->assertSame(1, $response->json('meta.current_page'), "page={$bad} harus menjadi halaman 1.");
        }
    }

    public function test_arbitrary_page_values_cannot_create_unbounded_cache_keys(): void
    {
        config(['cms_api.rate_limit_per_minute' => 1000]);
        $this->seedNews(3);

        foreach (['abc', 'xyz', '-5', '0', '1.5', 'zzz'] as $bad) {
            $this->withHmac()->getJson('/api/v1/cms/news?page='.urlencode($bad))->assertOk();
        }

        $this->assertCount(1, $this->pageKeys(), 'Semua page tidak valid harus memakai satu key cache (halaman 1).');
    }

    public function test_page_is_capped_to_a_maximum(): void
    {
        config(['cms_api.rate_limit_per_minute' => 1000]);
        $this->seedNews(3);

        $this->withHmac()->getJson('/api/v1/cms/news?page=5000')->assertOk();
        $this->withHmac()->getJson('/api/v1/cms/news?page=9000')->assertOk();

        $this->assertLessThanOrEqual(1, count($this->pageKeys()), 'Page di atas batas harus dijepit ke satu key yang sama.');
    }

    public function test_valid_pagination_still_works(): void
    {
        config(['cms_api.rate_limit_per_minute' => 1000]);
        $this->seedNews(14);

        $this->assertCount(12, $this->withHmac()->getJson('/api/v1/cms/news?page=1')->json('data'));
        $this->assertCount(2, $this->withHmac()->getJson('/api/v1/cms/news?page=2')->json('data'));
        $this->assertSame(2, $this->withHmac()->getJson('/api/v1/cms/news?page=2')->json('meta.current_page'));
    }

    public function test_articles_and_promotions_apply_the_same_page_rule(): void
    {
        config(['cms_api.rate_limit_per_minute' => 1000]);
        Article::withoutEvents(fn () => Article::create([
            'title' => ['id' => 'A', 'en' => 'A EN'],
            'slug' => ['id' => 'a', 'en' => 'a-en'],
            'content' => ['id' => 'c', 'en' => 'c EN'],
            'is_active' => 'yes',
        ]));
        Promotion::withoutEvents(fn () => Promotion::create([
            'title' => ['id' => 'P', 'en' => 'P EN'],
            'slug' => ['id' => 'p', 'en' => 'p-en'],
            'description' => ['id' => 'd', 'en' => 'd EN'],
            'is_active' => true,
        ]));

        foreach (['/api/v1/cms/articles', '/api/v1/cms/promotions'] as $path) {
            $response = $this->withHmac()->getJson($path.'?page=abc')->assertOk();
            $this->assertSame(1, $response->json('meta.current_page'));
        }
    }
}
