<?php

namespace Tests\Feature;

use App\Models\BannerPromotion;
use App\Models\News;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApiCacheCommitTest extends TestCase
{
    // Actual commits are needed here; RefreshDatabase's outer transaction never commits.
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        config(['filesystems.media_disk' => 'media-test']);
        Storage::fake('media-test');
    }

    public function test_commit_clears_locale_cache_repopulated_while_a_write_was_uncommitted(): void
    {
        $record = BannerPromotion::factory()->create();

        DB::transaction(function () use ($record): void {
            $record->setTranslation('title', 'en', 'Updated title')->save();
            // Represents a reader repopulating from the old committed snapshot before commit.
            Cache::put('rs_web_cms_api_banner_promotions_id', 'old snapshot');
            Cache::put('rs_web_cms_api_banner_promotions_en', 'old snapshot');
            $this->assertTrue(Cache::has('rs_web_cms_api_banner_promotions_id'));
        });

        $this->assertFalse(Cache::has('rs_web_cms_api_banner_promotions_id'));
        $this->assertFalse(Cache::has('rs_web_cms_api_banner_promotions_en'));
    }

    public function test_commit_rotates_the_paginated_generation_again_after_transaction_readers(): void
    {
        $record = News::factory()->create();
        $during = null;

        DB::transaction(function () use ($record, &$during): void {
            $record->setTranslation('title', 'en', 'Updated title')->save();
            $during = Cache::get('rs_web_cms_api_news_version');
            $this->assertNotNull($during);
        });

        $after = Cache::get('rs_web_cms_api_news_version');
        $this->assertNotNull($after);
        $this->assertNotSame($during, $after);
    }

    public function test_rollback_does_not_run_the_registered_after_commit_invalidation(): void
    {
        $record = News::factory()->create();
        $during = null;

        try {
            DB::transaction(function () use ($record, &$during): void {
                $record->setTranslation('title', 'en', 'Rolled back title')->save();
                $during = Cache::get('rs_web_cms_api_news_version');
                throw new \RuntimeException('Rollback test.');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Rollback test.', $exception->getMessage());
        }

        $this->assertSame($during, Cache::get('rs_web_cms_api_news_version'));
        $this->assertNotSame('Rolled back title', $record->fresh()->getTranslation('title', 'en', false));
    }
}
