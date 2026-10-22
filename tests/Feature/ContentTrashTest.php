<?php

namespace Tests\Feature;

use App\Actions\HardDeleteContent;
use App\Exceptions\HardDeleteForbiddenException;
use App\Filament\Resources\News\Pages\CreateNews;
use App\Filament\Resources\News\Pages\ListNews;
use App\Jobs\SendCacheInvalidationWebhookJob;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\BannerPromotion;
use App\Models\FacilityService;
use App\Models\News;
use App\Models\Promotion;
use App\Models\RoomFacility;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * I18N-9: hard delete hanya super_admin lewat jalur web; restore hanya super_admin;
 * marketing hanya soft delete.
 */
class ContentTrashTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        config(['filesystems.media_disk' => 'media-test']);
        Storage::fake('media-test');
        $this->seed(DatabaseSeeder::class);
        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])->assertExitCode(0);
    }

    private function actAs(string $email): User
    {
        $user = User::where('email', $email)->firstOrFail();
        $user->update(['must_change_password' => false]);
        $this->actingAs($user);

        return $user;
    }

    private function superAdmin(): User
    {
        return $this->actAs('admin@rs-elisabeth.com');
    }

    private function news(array $attributes = []): News
    {
        return News::factory()->create($attributes);
    }

    private function makeHardDeleteCall(News $news): void
    {
        app(HardDeleteContent::class)->handle($news);
    }

    public function test_marketing_can_soft_delete_but_cannot_restore_or_force_delete(): void
    {
        $this->actAs('marketing@rs-elisabeth.com');
        $news = $this->news();

        $this->assertTrue($news->delete());
        $this->assertSoftDeleted('news', ['id' => $news->id]);

        $trashed = News::withTrashed()->findOrFail($news->id);
        $this->assertFalse(auth()->user()->can('restore', $trashed));
        $this->assertFalse(auth()->user()->can('forceDelete', $trashed));
        $this->assertFalse(auth()->user()->can('restoreAny', News::class));
        $this->assertFalse(auth()->user()->can('forceDeleteAny', News::class));
    }

    public function test_super_admin_policy_allows_restore_and_force_delete(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin);
        $news = $this->news();
        $news->delete();

        $trashed = News::withTrashed()->findOrFail($news->id);
        $this->assertTrue($admin->can('restore', $trashed));
        $this->assertTrue($admin->can('forceDelete', $trashed));
    }

    public function test_it_staff_cannot_restore_or_force_delete_content(): void
    {
        $this->actAs('it@rs-elisabeth.com');
        $news = $this->news();
        $news->delete();

        $trashed = News::withTrashed()->findOrFail($news->id);
        $this->assertFalse(auth()->user()->can('restore', $trashed));
        $this->assertFalse(auth()->user()->can('forceDelete', $trashed));
    }

    public function test_model_force_delete_is_blocked_outside_the_sanctioned_path(): void
    {
        $this->superAdmin();
        $news = $this->news();

        $this->expectException(HardDeleteForbiddenException::class);
        $news->forceDelete();
    }

    public function test_model_force_delete_quietly_is_blocked(): void
    {
        $this->superAdmin();
        $news = $this->news();

        $this->expectException(HardDeleteForbiddenException::class);
        $news->forceDeleteQuietly();
    }

    public function test_builder_force_delete_is_blocked_and_keeps_the_row(): void
    {
        $this->superAdmin();
        $news = $this->news();
        $news->delete();

        try {
            News::withTrashed()->whereKey($news->id)->forceDelete();
            $this->fail('forceDelete via builder harus ditolak.');
        } catch (HardDeleteForbiddenException) {
        }

        $this->assertDatabaseHas('news', ['id' => $news->id]);
    }

    public function test_hard_delete_is_rejected_for_marketing_even_through_the_sanctioned_handler(): void
    {
        $this->actAs('marketing@rs-elisabeth.com');
        $news = $this->news();

        $this->expectException(HttpException::class);
        $this->makeHardDeleteCall($news);
    }

    public function test_super_admin_hard_delete_removes_row_audits_and_media(): void
    {
        $this->superAdmin();
        $news = $this->news(['image_path' => 'news/foto.webp']);
        Storage::disk('media-test')->put('news/foto.webp', 'x');
        $news->delete();

        $this->makeHardDeleteCall(News::withTrashed()->findOrFail($news->id));

        $this->assertDatabaseMissing('news', ['id' => $news->id]);
        Storage::disk('media-test')->assertMissing('news/foto.webp');

        $audit = AuditLog::where('action', 'content.force_deleted')->firstOrFail();
        $this->assertSame('news', $audit->metadata['module']);
        $this->assertSame('panel', $audit->metadata['source']);
        $this->assertArrayHasKey('slug_id', $audit->metadata);
        $this->assertArrayHasKey('slug_en', $audit->metadata);
    }

    public function test_media_delete_failure_does_not_roll_back_hard_delete(): void
    {
        $this->superAdmin();
        $news = $this->news(['image_path' => 'news/rusak.webp']);
        $news->delete();

        Storage::shouldReceive('disk')->andReturnSelf();
        Storage::shouldReceive('delete')->once()->andThrow(new \RuntimeException('disk tidak tersedia'));

        $deleted = app(HardDeleteContent::class)->handle(News::withTrashed()->findOrFail($news->id));

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('news', ['id' => $news->id]);
        $this->assertSame(1, AuditLog::where('action', 'content.force_deleted')->count());
    }

    public function test_hard_delete_is_rejected_from_console_even_for_super_admin(): void
    {
        $this->superAdmin();
        $news = $this->news();
        $news->delete();

        // Simulasi konteks console (tinker, artisan) dengan menonaktifkan pengecualian test.
        $this->app['env'] = 'production';
        $this->app->instance('env', 'production');

        try {
            $this->expectException(HardDeleteForbiddenException::class);
            app(HardDeleteContent::class)->handle(News::withTrashed()->findOrFail($news->id));
        } finally {
            $this->app->instance('env', 'testing');
            $this->assertDatabaseHas('news', ['id' => $news->id]);
        }
    }

    public function test_restore_by_super_admin_writes_audit_and_dispatches_single_webhook(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin);
        $news = $this->news();
        $news->delete();

        Bus::fake();
        News::withTrashed()->findOrFail($news->id)->restore();

        $this->assertDatabaseHas('news', ['id' => $news->id, 'deleted_at' => null]);
        $this->assertSame(1, AuditLog::where('action', 'content.restored')->count());

        $audit = AuditLog::where('action', 'content.restored')->firstOrFail();
        $this->assertSame('news', $audit->metadata['module']);
        $this->assertSame('panel', $audit->metadata['source']);
    }

    public function test_restore_action_is_hidden_from_marketing_and_trash_filter_is_unavailable(): void
    {
        $this->actAs('marketing@rs-elisabeth.com');
        $active = $this->news();

        Livewire::test(ListNews::class)
            ->assertTableFilterHidden('trashed')
            ->assertTableActionHidden('restore', $active);
    }

    public function test_force_delete_action_is_visible_only_for_trashed_records_to_super_admin(): void
    {
        $this->superAdmin();
        $active = $this->news();
        $trashed = $this->news();
        $trashed->delete();

        Livewire::test(ListNews::class)
            ->filterTable('trashed', true)
            ->assertTableActionVisible('forceDelete', News::withTrashed()->findOrFail($trashed->id))
            ->assertTableActionHidden('forceDelete', News::withTrashed()->findOrFail($active->id));
    }

    public function test_force_delete_action_is_hidden_from_marketing(): void
    {
        $this->actAs('marketing@rs-elisabeth.com');
        $active = $this->news();

        Livewire::test(ListNews::class)
            ->assertTableFilterHidden('trashed')
            ->assertTableActionHidden('forceDelete', $active);
    }

    public function test_hard_delete_from_table_removes_record_and_dispatches_single_webhook(): void
    {
        $this->superAdmin();
        $news = $this->news();
        $news->delete();

        Bus::fake();

        Livewire::test(ListNews::class)
            ->filterTable('trashed', true)
            ->callTableAction('forceDelete', News::withTrashed()->findOrFail($news->id));

        $this->assertDatabaseMissing('news', ['id' => $news->id]);
        Bus::assertDispatchedTimes(SendCacheInvalidationWebhookJob::class, 1);
    }

    public function test_slug_of_trashed_record_does_not_block_restore(): void
    {
        $this->superAdmin();
        $first = $this->news(['slug' => ['id' => 'berita-a', 'en' => 'news-a']]);
        $first->delete();

        $this->news(['slug' => ['id' => 'berita-b', 'en' => 'news-b']]);

        News::withTrashed()->findOrFail($first->id)->restore();

        $this->assertDatabaseHas('news', ['id' => $first->id, 'deleted_at' => null]);
    }

    public function test_hard_delete_webhook_is_sent_exactly_once(): void
    {
        $this->superAdmin();
        $news = $this->news();
        $news->delete();

        Bus::fake();
        $this->makeHardDeleteCall(News::withTrashed()->findOrFail($news->id));

        Bus::assertDispatchedTimes(SendCacheInvalidationWebhookJob::class, 1);
    }

    public function test_restore_webhook_is_sent_exactly_once(): void
    {
        $this->superAdmin();
        $news = $this->news();
        $news->delete();

        Bus::fake();
        News::withTrashed()->findOrFail($news->id)->restore();

        Bus::assertDispatchedTimes(SendCacheInvalidationWebhookJob::class, 1);
    }

    public function test_bulk_restore_writes_one_audit_and_webhook_per_record(): void
    {
        $this->superAdmin();
        $first = $this->news();
        $second = $this->news();
        $first->delete();
        $second->delete();

        Bus::fake();
        foreach (News::onlyTrashed()->get() as $record) {
            $record->restore();
        }

        $this->assertSame(2, AuditLog::where('action', 'content.restored')->count());
        Bus::assertDispatchedTimes(SendCacheInvalidationWebhookJob::class, 2);
    }

    public function test_marketing_form_shows_trash_notice_on_content_forms(): void
    {
        $this->actAs('marketing@rs-elisabeth.com');

        Livewire::test(CreateNews::class)
            ->assertSee('Hanya Super Admin yang dapat memulihkannya.');
    }

    public function test_bulk_hard_delete_action_removes_each_selected_record_with_audit(): void
    {
        $this->superAdmin();
        $first = $this->news();
        $second = $this->news();
        $first->delete();
        $second->delete();

        Livewire::test(ListNews::class)
            ->filterTable('trashed', true)
            ->callTableBulkAction('forceDelete', News::withTrashed()->whereIn('id', [$first->id, $second->id])->get());

        $this->assertDatabaseMissing('news', ['id' => $first->id]);
        $this->assertDatabaseMissing('news', ['id' => $second->id]);
        $this->assertSame(2, AuditLog::where('action', 'content.force_deleted')->count());
    }

    public function test_bulk_restore_action_restores_each_selected_record_with_audit_and_webhook(): void
    {
        $this->superAdmin();
        $first = $this->news();
        $second = $this->news();
        $first->delete();
        $second->delete();

        Bus::fake();

        Livewire::test(ListNews::class)
            ->filterTable('trashed', true)
            ->callTableBulkAction('restore', News::withTrashed()->whereIn('id', [$first->id, $second->id])->get());

        $this->assertDatabaseHas('news', ['id' => $first->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('news', ['id' => $second->id, 'deleted_at' => null]);
        $this->assertSame(2, AuditLog::where('action', 'content.restored')->count());
        Bus::assertDispatchedTimes(SendCacheInvalidationWebhookJob::class, 2);
    }

    public function test_restore_table_action_restores_trashed_record_with_audit(): void
    {
        $this->superAdmin();
        $news = $this->news();
        $news->delete();

        Livewire::test(ListNews::class)
            ->filterTable('trashed', true)
            ->callTableAction('restore', News::withTrashed()->findOrFail($news->id));

        $this->assertDatabaseHas('news', ['id' => $news->id, 'deleted_at' => null]);
        $this->assertSame(1, AuditLog::where('action', 'content.restored')->count());
    }

    /**
     * @return array<string, array{0: class-string, 1: string}>
     */
    public static function contentModels(): array
    {
        return [
            'news' => [News::class, 'News'],
            'article' => [Article::class, 'Article'],
            'promotion' => [Promotion::class, 'Promotion'],
            'banner' => [BannerPromotion::class, 'BannerPromotion'],
            'facility' => [FacilityService::class, 'FacilityService'],
            'room' => [RoomFacility::class, 'RoomFacility'],
        ];
    }

    #[DataProvider('contentModels')]
    public function test_restore_and_force_delete_policies_allow_only_super_admin(string $modelClass): void
    {
        $this->superAdmin();
        $record = $modelClass::factory()->create();
        $record->delete();
        $trashed = $modelClass::withTrashed()->findOrFail($record->id);

        $admin = auth()->user();
        $this->assertTrue($admin->can('restore', $trashed));
        $this->assertTrue($admin->can('forceDelete', $trashed));

        $this->actAs('marketing@rs-elisabeth.com');
        $marketing = auth()->user();
        $this->assertFalse($marketing->can('restore', $trashed));
        $this->assertFalse($marketing->can('forceDelete', $trashed));
    }

    public function test_seeded_permissions_match_the_role_matrix(): void
    {
        $permissions = DB::table('permissions')->pluck('name');

        $this->assertTrue($permissions->contains('ForceDelete:News'));
        $this->assertTrue($permissions->contains('Restore:News'));
    }
}
