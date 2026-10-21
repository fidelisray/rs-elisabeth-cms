<?php

namespace Tests\Feature;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TranslatedSlugSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        config(['filesystems.media_disk' => 'media-test']);
        Storage::fake('media-test');
    }

    public static function modules(): array
    {
        return array_map(fn ($case) => [$case[0], $case[1]], ContentTranslationSchemaTest::contentTables());
    }

    public static function moduleLocales(): array
    {
        $cases = [];

        foreach (self::modules() as $name => [$table, $model]) {
            foreach (['id', 'en'] as $locale) {
                $cases[$name.'-'.$locale] = [$table, $model, $locale];
            }
        }

        return $cases;
    }

    private function assertUniqueViolation(callable $write, string $index): void
    {
        try {
            DB::transaction($write);
        } catch (UniqueConstraintViolationException $exception) {
            $this->assertSame(DB::getDriverName() === 'pgsql' ? '23505' : '23000', $exception->errorInfo[0]);
            $this->assertStringContainsString($index, $exception->errorInfo[2]);

            return;
        }

        $this->fail('Database accepted a duplicate translated slug.');
    }

    #[DataProvider('moduleLocales')]
    public function test_duplicate_inserts_are_rejected_including_soft_deleted_records(string $table, string $model, string $locale): void
    {
        $record = $model::factory()->create(['slug' => ['id' => 'occupied-id', 'en' => 'occupied-en']]);
        $slugs = ['id' => 'new-id', 'en' => 'new-en'];
        $slugs[$locale] = $record->getTranslation('slug', $locale, false);
        $attributes = $model::factory()->make(['slug' => $slugs])->getAttributes();

        foreach ([false, true] as $trashed) {
            if ($trashed) {
                $record->delete();
            }

            $this->assertUniqueViolation(fn () => DB::table($table)->insert($attributes), $table.'_slug_'.$locale.'_unique');
            $this->assertSame(1, $model::withTrashed()->count());
        }
    }

    #[DataProvider('moduleLocales')]
    public function test_duplicate_updates_are_rejected_including_soft_deleted_records(string $table, string $model, string $locale): void
    {
        $occupied = $model::factory()->create(['slug' => ['id' => 'occupied-id', 'en' => 'occupied-en']]);
        $target = $model::factory()->create(['slug' => ['id' => 'target-id', 'en' => 'target-en']]);
        $slugs = $target->getTranslations('slug');
        $slugs[$locale] = $occupied->getTranslation('slug', $locale, false);

        foreach ([false, true] as $trashed) {
            if ($trashed) {
                $occupied->delete();
            }

            $this->assertUniqueViolation(
                fn () => DB::table($table)->where('id', $target->getKey())->update(['slug' => json_encode($slugs, JSON_THROW_ON_ERROR)]),
                $table.'_slug_'.$locale.'_unique'
            );
            $this->assertSame('target-'.$locale, $target->fresh()->getTranslation('slug', $locale, false));
        }
    }

    #[DataProvider('modules')]
    public function test_cross_locale_matches_self_edits_and_restore_are_allowed(string $table, string $model): void
    {
        $first = $model::factory()->create(['slug' => ['id' => 'shared-slug', 'en' => 'first-en']]);
        $second = $model::factory()->create(['slug' => ['id' => 'second-id', 'en' => 'shared-slug']]);
        $first->update(['slug' => ['id' => 'shared-slug', 'en' => 'first-en']]);
        $first->delete();
        $this->assertTrue($first->restore());
        $this->assertFalse($first->fresh()->trashed());
        $this->assertSame('shared-slug', $first->fresh()->getTranslation('slug', 'id', false));
        $this->assertSame('shared-slug', $second->fresh()->getTranslation('slug', 'en', false));
    }

    #[DataProvider('modules')]
    public function test_unique_indexes_cover_both_locales_and_include_trashed_rows(string $table, string $model): void
    {
        foreach (['id', 'en'] as $locale) {
            $name = $table.'_slug_'.$locale.'_unique';
            $sql = DB::getDriverName() === 'pgsql'
                ? DB::table('pg_indexes')->where('schemaname', 'public')->where('tablename', $table)->where('indexname', $name)->value('indexdef')
                : DB::table('sqlite_master')->where('type', 'index')->where('tbl_name', $table)->where('name', $name)->value('sql');

            $this->assertNotNull($sql, $name);
            $this->assertStringContainsString('UNIQUE', strtoupper($sql));
            $this->assertStringNotContainsString('WHERE', strtoupper($sql));
        }
    }

    public function test_the_same_slugs_can_be_used_in_different_modules(): void
    {
        foreach (self::modules() as [$table, $model]) {
            $record = $model::factory()->create(['slug' => ['id' => 'shared-id', 'en' => 'shared-en']]);
            $this->assertDatabaseHas($table, ['id' => $record->getKey()]);
        }
    }
}
