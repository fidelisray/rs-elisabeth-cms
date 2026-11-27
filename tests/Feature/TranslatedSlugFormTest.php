<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SeedsTestRolesAndUsers;
use Tests\TestCase;

class TranslatedSlugFormTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTestRolesAndUsers;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        config(['filesystems.media_disk' => 'media-test']);
        Storage::fake('media-test');
        $this->seedDefaultTestUsersAndRoles();
        $marketing = User::where('email', 'marketing@rs-elisabeth.com')->firstOrFail();
        $marketing->update(['must_change_password' => false]);
        $this->actingAs($marketing);
    }

    public static function modules(): array
    {
        return ContentTranslationFormTest::modules();
    }

    public static function modulesAndTrashStates(): array
    {
        $cases = [];

        foreach (self::modules() as $key => $case) {
            foreach ([false, true] as $trashed) {
                $cases[$key.($trashed ? '-trashed' : '-active')] = [...$case, $trashed];
            }
        }

        return $cases;
    }

    private function createForm(string $resource, string $model, string $titleField, ?string $bodyField, array $extra)
    {
        $state = [
            "{$titleField}.id" => 'Konten Baru',
            'slug.id' => 'new-id',
            "{$titleField}.en" => 'New Content',
            'slug.en' => 'new-en',
            'image_path' => UploadedFile::fake()->image('content.png', 640, 640),
        ];

        if ($bodyField) {
            $state["{$bodyField}.id"] = '<p>Konten Indonesia</p>';
            $state["{$bodyField}.en"] = '<p>English content</p>';
        }

        foreach (['shorts', 'short_description', 'tags', 'tagline'] as $field) {
            if (array_key_exists($field, $extra)) {
                $state["{$field}.id"] = $extra[$field];
                $state["{$field}.en"] = 'English '.$field;
            }
        }

        if (array_key_exists('category', $extra)) {
            if ($titleField === 'title') {
                $state['category.id'] = $extra['category'];
                $state['category.en'] = 'New category';
            } else {
                $state['category'] = $extra['category'];
            }
        }

        foreach (['author', 'confirm_no_end_date'] as $sharedField) {
            if (array_key_exists($sharedField, $extra)) {
                $state[$sharedField] = $extra[$sharedField];
            }
        }

        $form = Livewire::test("App\\Filament\\Resources\\{$resource}\\Pages\\Create{$model}")
            ->fillForm($state);

        return $form->assertHasNoFormErrors();
    }

    #[DataProvider('modulesAndTrashStates')]
    public function test_inactive_language_conflicts_are_rejected_including_soft_deleted_records(string $resource, string $model, string $titleField, ?string $bodyField, array $extra, bool $trashed): void
    {
        $class = "App\\Models\\{$model}";
        $form = $this->createForm($resource, $model, $titleField, $bodyField, $extra);
        $occupied = $class::factory()->create(['slug' => ['id' => 'occupied-id', 'en' => 'new-en']]);

        if ($trashed) {
            $occupied->delete();
        }

        $form->call('create');
        $this->assertSame(1, $class::withTrashed()->count());
        $this->assertFalse($class::withTrashed()->where('slug->id', 'new-id')->exists());
    }

    #[DataProvider('modulesAndTrashStates')]
    public function test_active_language_conflicts_show_a_clear_validation_message(string $resource, string $model, string $titleField, ?string $bodyField, array $extra, bool $trashed): void
    {
        $class = "App\\Models\\{$model}";
        $form = $this->createForm($resource, $model, $titleField, $bodyField, $extra);
        $occupied = $class::factory()->create(['slug' => ['id' => 'new-id', 'en' => 'occupied-en']]);

        if ($trashed) {
            $occupied->delete();
        }

        if ($trashed) {
            $form->call('create')->assertHasFormErrors(['slug.id'])->assertNotified();
        } else {
            $form->call('create')->assertHasFormErrors(['slug.id']);
            $this->assertSame('Identifier (Slug) sudah digunakan. Pilih identifier lain.', $form->instance()->getErrorBag()->first('data.slug.id'));
        }
        $this->assertSame(1, $class::withTrashed()->count());
    }

    #[DataProvider('modules')]
    public function test_editing_without_changing_slugs_is_allowed(string $resource, string $model, string $titleField, ?string $bodyField, array $extra): void
    {
        $class = "App\\Models\\{$model}";
        $record = $class::factory()->create(['image_path' => 'test.webp']);
        Storage::disk('media-test')->put('test.webp', 'test image');
        $slugs = $record->getTranslations('slug');

        Livewire::test("App\\Filament\\Resources\\{$resource}\\Pages\\Edit{$model}", ['record' => $record->getRouteKey()])
            ->fillForm(["{$titleField}.id" => 'Nama Diperbarui'])->call('save')->assertHasNoFormErrors();

        $this->assertSame('Nama Diperbarui', $record->fresh()->getTranslation($titleField, 'id', false));
        $this->assertEquals($slugs, $record->fresh()->getTranslations('slug'));
    }

    #[DataProvider('modules')]
    public function test_late_database_conflict_on_create_becomes_a_slug_validation_error(string $resource, string $model, string $titleField, ?string $bodyField, array $extra): void
    {
        $class = "App\\Models\\{$model}";
        $form = $this->createForm($resource, $model, $titleField, $bodyField, $extra);
        $dispatcher = $class::getEventDispatcher();
        $class::setEventDispatcher(clone $dispatcher);
        $injected = false;

        try {
            $class::creating(function ($record) use (&$injected): void {
                $attributes = $record->getAttributes();
                unset($attributes['id']);
                $attributes['slug'] = json_encode(['id' => 'late-rival-id', 'en' => 'new-en'], JSON_THROW_ON_ERROR);
                DB::table($record->getTable())->insert($attributes);
                $injected = true;
            });

            $form->call('create')->assertHasFormErrors(['slug.en'])
                ->assertFormSet(['slug.en' => 'new-en']);
            $this->assertTrue($injected, 'The competing insert must occur after validation.');
            $this->assertSame(0, $class::withTrashed()->count(), 'The failed write must roll back its transaction.');
        } finally {
            $class::setEventDispatcher($dispatcher);
        }
    }

    #[DataProvider('modules')]
    public function test_late_database_conflict_on_edit_preserves_saved_content(string $resource, string $model, string $titleField, ?string $bodyField, array $extra): void
    {
        $class = "App\\Models\\{$model}";
        $record = $class::factory()->create(['image_path' => 'test.webp']);
        Storage::disk('media-test')->put('test.webp', 'test image');
        $original = $record->getTranslations('slug');
        $form = Livewire::test("App\\Filament\\Resources\\{$resource}\\Pages\\Edit{$model}", ['record' => $record->getRouteKey()])
            ->fillForm(['slug.en' => 'new-en']);
        $dispatcher = $class::getEventDispatcher();
        $class::setEventDispatcher(clone $dispatcher);
        $injected = false;

        try {
            $class::updating(function ($candidate) use (&$injected): void {
                $attributes = $candidate->getAttributes();
                unset($attributes['id']);
                $attributes['slug'] = json_encode(['id' => 'late-rival-id', 'en' => 'new-en'], JSON_THROW_ON_ERROR);
                DB::table($candidate->getTable())->insert($attributes);
                $injected = true;
            });

            $form->call('save')->assertHasFormErrors(['slug.en'])
                ->assertFormSet(['slug.en' => 'new-en']);
            $this->assertTrue($injected);
            $this->assertSame(1, $class::withTrashed()->count());
            $this->assertEquals($original, $record->fresh()->getTranslations('slug'));
        } finally {
            $class::setEventDispatcher($dispatcher);
        }
    }

    public function test_auto_generated_slug_is_validated_and_blank_title_is_rejected(): void
    {
        $case = self::modules()['news'];
        $form = $this->createForm(...$case);
        News::factory()->create(['slug' => ['id' => 'occupied-id', 'en' => 'new-content']]);
        $form->fillForm(['slug.en' => ''])->call('create')->assertHasFormErrors(['slug.en']);
        $this->assertSame(1, News::count());

        $form->fillForm(['title.en' => '', 'slug.en' => 'unused-slug'])->call('create')->assertHasFormErrors(['title.en']);
        $this->assertSame(1, News::count());
    }

    public function test_non_slug_unique_errors_are_not_swallowed(): void
    {
        $form = $this->createForm(...self::modules()['news']);
        $occupied = News::factory()->create();
        $dispatcher = News::getEventDispatcher();
        News::setEventDispatcher(clone $dispatcher);

        try {
            News::creating(fn ($record) => $record->setAttribute('id', $occupied->getKey()));
            $this->expectException(UniqueConstraintViolationException::class);
            $form->call('create');
        } finally {
            News::setEventDispatcher($dispatcher);
        }
    }
}
