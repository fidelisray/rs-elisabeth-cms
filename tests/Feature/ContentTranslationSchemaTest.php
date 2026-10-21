<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\BannerPromotion;
use App\Models\FacilityService;
use App\Models\News;
use App\Models\Promotion;
use App\Models\RoomFacility;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\GeneratesHmacHeaders;

class ContentTranslationSchemaTest extends TestCase
{
    use GeneratesHmacHeaders, RefreshDatabase;

    public static function contentTables(): array
    {
        return [
            'news' => ['news', News::class, ['title', 'slug', 'content'], ['shorts', 'category']],
            'articles' => ['articles', Article::class, ['title', 'slug', 'content'], ['shorts', 'tags']],
            'promotions' => ['promotions', Promotion::class, ['title', 'slug', 'description'], ['shorts']],
            'facilities' => ['facility_services', FacilityService::class, ['name', 'slug', 'description'], ['short_description', 'wa_link_text', 'wa_prefilled_message']],
            'rooms' => ['room_facilities', RoomFacility::class, ['name', 'slug', 'tagline', 'description'], ['whatsapp_text']],
            'banners' => ['banner_promotions', BannerPromotion::class, ['title', 'slug'], []],
        ];
    }

    private function requirePostgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('JSONB CHECK constraints are verified by the PostgreSQL test job.');
        }
    }

    private function validRecord(string $table, array $required): array
    {
        $record = [];

        foreach ($required as $column) {
            $record[$column] = json_encode(['id' => 'Nilai Indonesia', 'en' => 'English value'], JSON_THROW_ON_ERROR);
        }

        if ($table === 'banner_promotions') {
            $record['image_path'] = 'banners/schema-test.webp';
        }

        return $record;
    }

    private function invalidTranslations(): array
    {
        return [
            'sql-null' => null,
            'json-null' => 'null',
            'missing-id' => '{"en":"English"}',
            'missing-en' => '{"id":"Indonesia"}',
            'null-id' => '{"id":null,"en":"English"}',
            'null-en' => '{"id":"Indonesia","en":null}',
            'empty-id' => '{"id":"","en":"English"}',
            'empty-en' => '{"id":"Indonesia","en":""}',
            'whitespace' => json_encode(['id' => " \t\n\r ", 'en' => 'English'], JSON_THROW_ON_ERROR),
            'whitespace-en' => json_encode(['id' => 'Indonesia', 'en' => " \t\n\r "], JSON_THROW_ON_ERROR),
            'number' => '{"id":42,"en":"English"}',
            'boolean' => '{"id":"Indonesia","en":true}',
            'array' => '{"id":[],"en":"English"}',
            'object' => '{"id":"Indonesia","en":{}}',
            'root-array' => '["Indonesia","English"]',
            'root-string' => '"Indonesia"',
            'root-number' => '42',
            'root-boolean' => 'true',
            'empty-object' => '{}',
        ];
    }

    private function assertRejected(callable $write, string $column, string $case): void
    {
        try {
            // A savepoint isolates the expected error from RefreshDatabase's outer transaction.
            DB::transaction($write);
        } catch (QueryException $exception) {
            $this->assertSame($case === 'sql-null' ? '23502' : '23514', $exception->errorInfo[0], $case);
            $this->assertStringContainsString($column, $exception->getMessage(), $case);

            return;
        }

        $this->fail("Database accepted invalid translation: {$column} ({$case}).");
    }

    #[DataProvider('contentTables')]
    public function test_required_fields_reject_invalid_translations_on_insert(string $table, string $model, array $required, array $optional): void
    {
        $this->requirePostgres();
        $valid = $this->validRecord($table, $required);

        foreach ($required as $column) {
            foreach ($this->invalidTranslations() as $case => $value) {
                $this->assertRejected(fn () => DB::table($table)->insert([...$valid, $column => $value]), $column, $case);
            }
        }
    }

    #[DataProvider('contentTables')]
    public function test_required_fields_reject_invalid_translations_on_update(string $table, string $model, array $required, array $optional): void
    {
        $this->requirePostgres();
        $id = DB::table($table)->insertGetId($this->validRecord($table, $required));

        foreach ($required as $column) {
            foreach ($this->invalidTranslations() as $case => $value) {
                $this->assertRejected(fn () => DB::table($table)->where('id', $id)->update([$column => $value]), $column, $case);
            }
        }

        $this->assertDatabaseHas($table, ['id' => $id]);
    }

    #[DataProvider('contentTables')]
    public function test_optional_fields_allow_null_but_require_both_locales_when_present(string $table, string $model, array $required, array $optional): void
    {
        $this->requirePostgres();
        $id = DB::table($table)->insertGetId($this->validRecord($table, $required));
        $this->assertDatabaseHas($table, ['id' => $id]);

        foreach ($optional as $column) {
            DB::table($table)->where('id', $id)->update([$column => json_encode(['id' => 'Opsional', 'en' => 'Optional'], JSON_THROW_ON_ERROR)]);
            DB::table($table)->where('id', $id)->update([$column => null]);

            foreach ($this->invalidTranslations() as $case => $value) {
                if ($case !== 'sql-null') {
                    $this->assertRejected(fn () => DB::table($table)->where('id', $id)->update([$column => $value]), $column, $case);
                }
            }
        }
    }

    #[DataProvider('contentTables')]
    public function test_columns_and_factory_round_trip_are_consistent(string $table, string $model, array $required, array $optional): void
    {
        Bus::fake();
        config(['filesystems.media_disk' => 'media-test']);
        Storage::fake('media-test');

        $columns = collect(Schema::getColumns($table))->keyBy('name');

        foreach ([...$required, ...$optional] as $column) {
            $this->assertSame(in_array($column, $optional, true), $columns[$column]['nullable'], "{$table}.{$column}");

            if (DB::getDriverName() === 'pgsql') {
                $this->assertSame('jsonb', $columns[$column]['type_name'], "{$table}.{$column}");
            }
        }

        $record = $model::factory()->create()->fresh();

        foreach ([...$required, ...$optional] as $column) {
            $this->assertTrue($record->isTranslatableAttribute($column), "{$model}::{$column}");
            $translations = $record->getTranslations($column);
            $this->assertEqualsCanonicalizing(['id', 'en'], array_keys($translations));
            $this->assertNotSame('', trim($translations['id']));
            $this->assertNotSame('', trim($translations['en']));
        }

        foreach ($optional as $column) {
            $record->setTranslation($column, 'id', '');
            $record->setTranslation($column, 'en', '');
            $record->save();
            $this->assertNull($record->fresh()->getRawOriginal($column));
        }
    }

    #[DataProvider('contentTables')]
    public function test_api_reads_bilingual_factory_data_in_both_locales(string $table, string $model, array $required, array $optional): void
    {
        Bus::fake();
        config(['filesystems.media_disk' => 'media-test']);
        Storage::fake('media-test');
        $record = $model::factory()->create([$table === 'news' ? 'is_published' : 'is_active' => true]);
        $path = match ($table) {
            'facility_services' => 'facilities',
            'room_facilities' => 'room-facilities',
            'banner_promotions' => 'banner-promotions',
            default => $table,
        };

        foreach (['id', 'en'] as $locale) {
            $response = $this->withHmac()->getJson('/api/v1/cms/'.$path.'?lang='.$locale)
                ->assertOk()->assertHeader('Content-Language', $locale);
            $this->assertSame($record->getKey(), $response->json('data.0.id'));

            foreach ([...$required, ...$optional] as $column) {
                $key = $column === 'shorts' ? 'excerpt' : $column;
                $this->assertSame($record->getTranslation($column, $locale, false), $response->json('data.0.'.$key), "{$table}.{$column}.{$locale}");
            }
        }
    }
}
