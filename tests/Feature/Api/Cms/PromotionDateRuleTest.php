<?php

namespace Tests\Feature\Api\Cms;

use App\Filament\Resources\Promotions\Pages\CreatePromotion;
use App\Models\Promotion;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\GeneratesHmacHeaders;

/**
 * MVP-2.2 (K-12, K-16): tanggal promosi ditegakkan di API, bukan di frontend.
 * "Hari ini" dihitung memakai APP_TIMEZONE (Asia/Jakarta).
 */
class PromotionDateRuleTest extends TestCase
{
    use GeneratesHmacHeaders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'Asia/Jakarta']);
        Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function promo(array $attributes = []): Promotion
    {
        static $n = 0;
        $n++;

        return Promotion::create(array_merge([
            'title' => ['id' => "Promo {$n}", 'en' => "Promotion {$n}"],
            'slug' => ['id' => $attributes['slug'] ?? "promo-{$n}", 'en' => ($attributes['slug'] ?? "promo-{$n}").'-en'],
            'description' => ['id' => 'Deskripsi', 'en' => 'Description'],
            'is_active' => true,
        ], array_diff_key($attributes, ['slug' => true])));
    }

    private function listedSlugs(): array
    {
        return collect($this->withHmac()->getJson('/api/v1/cms/promotions')->assertOk()->json('data'))
            ->pluck('slug')->all();
    }

    // -------------------------------------------------------------- daftar

    public function test_future_start_date_is_not_listed(): void
    {
        $this->promo(['slug' => 'akan-datang', 'start_date' => '2026-10-21', 'end_date' => '2026-10-30']);

        $this->assertNotContains('akan-datang', $this->listedSlugs());
    }

    public function test_past_end_date_is_not_listed(): void
    {
        $this->promo(['slug' => 'sudah-lewat', 'start_date' => '2026-10-01', 'end_date' => '2026-10-19']);

        $this->assertNotContains('sudah-lewat', $this->listedSlugs());
    }

    public function test_promo_ending_today_is_still_listed_inclusive(): void
    {
        $this->promo(['slug' => 'berakhir-hari-ini', 'start_date' => '2026-10-01', 'end_date' => '2026-10-20']);

        $this->assertContains('berakhir-hari-ini', $this->listedSlugs());
    }

    public function test_promo_starting_today_is_listed_inclusive(): void
    {
        $this->promo(['slug' => 'mulai-hari-ini', 'start_date' => '2026-10-20', 'end_date' => '2026-10-31']);

        $this->assertContains('mulai-hari-ini', $this->listedSlugs());
    }

    public function test_promo_without_any_date_is_listed(): void
    {
        $this->promo(['slug' => 'tanpa-tanggal', 'start_date' => null, 'end_date' => null]);

        $this->assertContains('tanpa-tanggal', $this->listedSlugs());
    }

    public function test_empty_start_date_means_effective_immediately(): void
    {
        $this->promo(['slug' => 'tanpa-mulai', 'start_date' => null, 'end_date' => '2026-10-25']);

        $this->assertContains('tanpa-mulai', $this->listedSlugs());
    }

    public function test_empty_end_date_means_no_end(): void
    {
        $this->promo(['slug' => 'tanpa-akhir', 'start_date' => '2026-10-01', 'end_date' => null]);

        $this->assertContains('tanpa-akhir', $this->listedSlugs());
    }

    public function test_inactive_promo_is_never_listed_even_within_range(): void
    {
        $this->promo(['slug' => 'nonaktif', 'is_active' => false, 'start_date' => '2026-10-01', 'end_date' => '2026-10-30']);

        $this->assertNotContains('nonaktif', $this->listedSlugs());
    }

    // -------------------------------------------------------------- detail

    public function test_detail_of_expired_promo_is_not_found_by_slug_and_by_id(): void
    {
        $promo = $this->promo(['slug' => 'kedaluwarsa', 'start_date' => '2026-10-01', 'end_date' => '2026-10-19']);

        $this->withHmac()->getJson('/api/v1/cms/promotions/kedaluwarsa')->assertNotFound();
        $this->withHmac()->getJson('/api/v1/cms/promotions/'.$promo->id)->assertNotFound();
    }

    public function test_detail_of_future_promo_is_not_found(): void
    {
        $this->promo(['slug' => 'nanti', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30']);

        $this->withHmac()->getJson('/api/v1/cms/promotions/nanti')->assertNotFound();
    }

    public function test_detail_of_running_promo_is_found(): void
    {
        $this->promo(['slug' => 'berjalan', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31']);

        $this->withHmac()->getJson('/api/v1/cms/promotions/berjalan')
            ->assertOk()
            ->assertJsonPath('data.slug', 'berjalan');
    }

    public function test_detail_without_dates_is_found(): void
    {
        $this->promo(['slug' => 'selalu', 'start_date' => null, 'end_date' => null]);

        $this->withHmac()->getJson('/api/v1/cms/promotions/selalu')->assertOk();
    }

    // ------------------------------------------------------- zona waktu

    public function test_today_uses_jakarta_timezone_not_utc(): void
    {
        // 21 Okt 2026 02:00 WIB masih 20 Okt 19:00 UTC. Promo berakhir 20 Okt:
        // di WIB sudah lewat, di UTC masih berjalan. Aturan harus mengikuti WIB.
        Carbon::setTestNow(Carbon::parse('2026-10-21 02:00:00', 'Asia/Jakarta'));

        $this->promo(['slug' => 'berakhir-kemarin-wib', 'start_date' => '2026-10-01', 'end_date' => '2026-10-20']);

        $this->assertNotContains('berakhir-kemarin-wib', $this->listedSlugs());
    }

    // ------------------------------------------------------------ cache

    public function test_list_cache_changes_when_the_day_changes(): void
    {
        $this->promo(['slug' => 'habis-besok', 'start_date' => '2026-10-01', 'end_date' => '2026-10-20']);

        $this->assertContains('habis-besok', $this->listedSlugs());

        // Pergantian hari TANPA ada perubahan data (tidak ada event model).
        Carbon::setTestNow(Carbon::parse('2026-10-21 00:05:00', 'Asia/Jakarta'));

        $this->assertNotContains('habis-besok', $this->listedSlugs(), 'Cache daftar harus berganti saat pergantian hari.');
    }

    public function test_cache_key_contains_the_date(): void
    {
        $this->promo(['slug' => 'cache-key']);

        $this->withHmac()->getJson('/api/v1/cms/promotions')->assertOk();

        $keys = collect(array_keys($this->arrayCacheStore()))->filter(fn ($k) => str_contains($k, 'rs_web_cms_api_promotions_v'));

        $this->assertTrue($keys->contains(fn ($k) => str_contains($k, '2026-10-20')), 'Key cache daftar harus memuat tanggal hari ini.');
    }

    public function test_page_parameter_still_works_with_date_filter(): void
    {
        foreach (range(1, 14) as $i) {
            $this->promo(['slug' => "massal-{$i}", 'start_date' => '2026-10-01', 'end_date' => '2026-10-31']);
        }

        $page1 = $this->withHmac()->getJson('/api/v1/cms/promotions?page=1')->assertOk();
        $page2 = $this->withHmac()->getJson('/api/v1/cms/promotions?page=2')->assertOk();

        $this->assertCount(12, $page1->json('data'));
        $this->assertCount(2, $page2->json('data'));
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayCacheStore(): array
    {
        $store = Cache::getStore();
        $reflection = new \ReflectionProperty($store, 'storage');

        return $reflection->getValue($store);
    }

    // ------------------------------------------------------------- form

    private function actAsMarketing(): void
    {
        // Form mengunggah poster ke disk media; gunakan disk palsu agar tidak menyentuh MinIO.
        config(['filesystems.media_disk' => 'media-test']);
        Storage::fake('media-test');

        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'marketing@rs-elisabeth.com')->first();
        $user->update(['must_change_password' => false]);
        $this->actingAs($user);
    }

    private function validForm(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Promo Form',
            'slug' => 'promo-form',
            'shorts' => 'Ringkas',
            'description' => '<p>Deskripsi</p>',
            'start_date' => '2026-10-20',
            'end_date' => '2026-10-31',
            'image_path' => UploadedFile::fake()->image('poster.png', 400, 500),
            'is_active' => true,
        ], $overrides);
    }

    public function test_form_allows_empty_dates(): void
    {
        $this->actAsMarketing();

        Livewire::test(CreatePromotion::class)
            ->fillForm($this->validForm(['start_date' => null, 'end_date' => null, 'confirm_no_end_date' => true]))
            ->call('create')
            ->assertHasNoFormErrors(['start_date', 'end_date']);
    }

    public function test_form_rejects_end_date_before_start_date(): void
    {
        $this->actAsMarketing();

        Livewire::test(CreatePromotion::class)
            ->fillForm($this->validForm(['start_date' => '2026-10-25', 'end_date' => '2026-10-20']))
            ->call('create')
            ->assertHasFormErrors(['end_date']);
    }

    public function test_form_accepts_end_date_equal_to_start_date(): void
    {
        $this->actAsMarketing();

        Livewire::test(CreatePromotion::class)
            ->fillForm($this->validForm(['start_date' => '2026-10-25', 'end_date' => '2026-10-25']))
            ->call('create')
            ->assertHasNoFormErrors(['end_date']);
    }

    public function test_empty_end_date_requires_confirmation_checkbox(): void
    {
        $this->actAsMarketing();

        Livewire::test(CreatePromotion::class)
            ->fillForm($this->validForm(['end_date' => null, 'confirm_no_end_date' => false]))
            ->call('create')
            ->assertHasFormErrors(['confirm_no_end_date']);

        $this->assertSame(0, Promotion::whereTranslatedSlug('promo-form')->count());
    }

    public function test_empty_end_date_is_saved_when_checkbox_is_confirmed(): void
    {
        $this->actAsMarketing();

        Livewire::test(CreatePromotion::class)
            ->fillForm($this->validForm(['end_date' => null, 'confirm_no_end_date' => true]))
            ->set('activeLocale', 'en')
            ->fillForm([
                'title' => 'Promo Form EN',
                'slug' => 'promo-form-en',
                'shorts' => 'Short EN',
                'description' => '<p>Description EN</p>',
                'confirm_no_end_date' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $saved = Promotion::whereTranslatedSlug('promo-form')->first();
        $this->assertNotNull($saved);
        $this->assertNull($saved->end_date);
    }

    public function test_checkbox_is_not_required_when_end_date_is_filled(): void
    {
        $this->actAsMarketing();

        Livewire::test(CreatePromotion::class)
            ->fillForm($this->validForm(['end_date' => '2026-10-31']))
            ->call('create')
            ->assertHasNoFormErrors(['confirm_no_end_date']);
    }

    public function test_confirmation_checkbox_is_not_a_database_column(): void
    {
        $this->actAsMarketing();

        Livewire::test(CreatePromotion::class)
            ->fillForm($this->validForm(['end_date' => null, 'confirm_no_end_date' => true]))
            ->set('activeLocale', 'en')
            ->fillForm([
                'title' => 'Promo Form EN',
                'slug' => 'promo-form-en',
                'shorts' => 'Short EN',
                'description' => '<p>Description EN</p>',
                'confirm_no_end_date' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertFalse(Schema::hasColumn('promotions', 'confirm_no_end_date'));
    }
}
