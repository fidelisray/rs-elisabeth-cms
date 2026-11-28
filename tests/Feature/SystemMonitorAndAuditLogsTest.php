<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\AuditLogsPage;
use App\Filament\Pages\SystemMonitorPage;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\DoctorApiService;
use App\Services\DoctorSyncService;
use App\Services\IntegrationTelemetryService;
use App\Services\WebFrontendWebhookService;
use App\Support\ApiCacheInvalidator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\SeedsTestRolesAndUsers;
use Tests\TestCase;

class SystemMonitorAndAuditLogsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTestRolesAndUsers;

    private User $superAdmin;

    private User $marketing;

    private User $staffIt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDefaultTestUsersAndRoles();
        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])->assertExitCode(0);

        $this->superAdmin = User::where('email', 'admin@rs-elisabeth.com')->firstOrFail();
        $this->marketing = User::where('email', 'marketing@rs-elisabeth.com')->firstOrFail();
        $this->staffIt = User::where('email', 'it@rs-elisabeth.com')->firstOrFail();

        foreach ([$this->superAdmin, $this->marketing, $this->staffIt] as $user) {
            $user->update(['must_change_password' => false]);
        }

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_permission_seeder_registers_additive_system_monitor_permissions_for_super_admin(): void
    {
        $this->assertTrue(Permission::where('name', 'View:SystemMonitor')->exists());
        $this->assertTrue(Permission::where('name', 'Trigger:WebCacheInvalidation')->exists());

        $this->assertTrue($this->superAdmin->can('View:SystemMonitor'));
        $this->assertTrue($this->superAdmin->can('Trigger:WebCacheInvalidation'));
        $this->assertFalse($this->marketing->can('View:SystemMonitor'));
        $this->assertFalse($this->staffIt->can('View:SystemMonitor'));
    }

    public function test_audit_logs_page_enforces_strict_super_admin_only_access(): void
    {
        // Meskipun Staff IT diberi izin View:SystemMonitor, Audit Logs tetap 403 keras.
        $this->staffIt->givePermissionTo('View:SystemMonitor');

        $this->actingAs($this->marketing)
            ->get('/admin/audit-logs')
            ->assertForbidden();

        $this->flushSession();
        app()->forgetScopedInstances();

        $this->actingAs($this->staffIt)
            ->get('/admin/audit-logs')
            ->assertForbidden();

        $this->flushSession();
        app()->forgetScopedInstances();

        $this->actingAs($this->superAdmin)
            ->get('/admin/audit-logs')
            ->assertOk()
            ->assertSee('data-rse-audit-logs', false);
    }

    public function test_audit_logs_page_filters_by_action_actor_type_subject_type_date_and_redacts_metadata(): void
    {
        $article = Article::factory()->create();

        $userLog = AuditLog::create([
            'actor_user_id' => $this->superAdmin->id,
            'actor_type' => 'user',
            'actor_email' => $this->superAdmin->email,
            'action' => 'content.restored',
            'subject_type' => Article::class,
            'subject_id' => $article->id,
            'metadata' => [
                'module' => 'articles',
                'source' => 'panel',
                'secret_token' => 'SHOULD-BE-REDACTED',
            ],
            'created_at' => now()->subDays(2),
        ]);

        $consoleLog = AuditLog::create([
            'actor_user_id' => null,
            'actor_type' => 'console',
            'actor_email' => null,
            'action' => 'cache.invalidated_manually',
            'subject_type' => null,
            'subject_id' => null,
            'metadata' => [
                'module' => 'all',
                'webhook_status' => 'success',
            ],
            'created_at' => now(),
        ]);

        $this->actingAs($this->superAdmin);

        // Ringkasan metadata di tabel wajib menyensor kunci sensitif
        $summary = AuditLogsPage::summarizeMetadata($userLog);
        $this->assertStringContainsString('module: articles', $summary);
        $this->assertStringContainsString('[REDACTED]', $summary);
        $this->assertStringNotContainsString('SHOULD-BE-REDACTED', $summary);

        // Filter action
        Livewire::test(AuditLogsPage::class)
            ->filterTable('action', 'cache.invalidated_manually')
            ->assertCanSeeTableRecords([$consoleLog])
            ->assertCanNotSeeTableRecords([$userLog]);

        // Filter actor_type
        Livewire::test(AuditLogsPage::class)
            ->filterTable('actor_type', 'console')
            ->assertCanSeeTableRecords([$consoleLog])
            ->assertCanNotSeeTableRecords([$userLog]);

        // Filter subject_type (model spesifik dan __none__)
        Livewire::test(AuditLogsPage::class)
            ->filterTable('subject_type', Article::class)
            ->assertCanSeeTableRecords([$userLog])
            ->assertCanNotSeeTableRecords([$consoleLog]);

        Livewire::test(AuditLogsPage::class)
            ->filterTable('subject_type', '__none__')
            ->assertCanSeeTableRecords([$consoleLog])
            ->assertCanNotSeeTableRecords([$userLog]);

        // Filter rentang tanggal
        Livewire::test(AuditLogsPage::class)
            ->filterTable('created_at', [
                'from' => now()->subDay()->toDateString(),
                'until' => now()->toDateString(),
            ])
            ->assertCanSeeTableRecords([$consoleLog])
            ->assertCanNotSeeTableRecords([$userLog]);
    }

    public function test_system_monitor_rbac_separates_view_from_manual_cache_invalidation(): void
    {
        // Tanpa permission View:SystemMonitor -> 403
        $this->actingAs($this->staffIt)
            ->get('/admin/system-monitor')
            ->assertForbidden();

        // Dengan View:SystemMonitor saja -> 200 tetapi tombol Invalidasi tersembunyi
        $this->staffIt->givePermissionTo('View:SystemMonitor');

        $this->actingAs($this->staffIt)
            ->get('/admin/system-monitor')
            ->assertOk()
            ->assertSee('data-rse-system-monitor', false);

        Livewire::test(SystemMonitorPage::class)
            ->assertActionHidden('invalidateWebCache');

        // Dengan Trigger:WebCacheInvalidation -> tombol terlihat
        $this->staffIt->givePermissionTo('Trigger:WebCacheInvalidation');

        Livewire::test(SystemMonitorPage::class)
            ->assertActionVisible('invalidateWebCache');
    }

    public function test_manual_cache_invalidation_reports_honest_outcomes_records_audit_and_enforces_rate_limit(): void
    {
        $this->actingAs($this->superAdmin);
        RateLimiter::clear('system-monitor:invalidate-cache:'.$this->superAdmin->id);

        // 1. Outcome unconfigured (webhook URL kosong)
        Config::set('web_frontend.webhook_url', '');
        Config::set('web_frontend.cons_id', '');
        Config::set('web_frontend.secret_key', '');

        $versionBefore = Cache::rememberForever('rs_web_cms_api_news_version', fn (): string => 'v-before');

        Livewire::test(SystemMonitorPage::class)
            ->callAction('invalidateWebCache', ['module' => 'news'])
            ->assertNotified('Cache lokal dihapus, tetapi webhook belum dikonfigurasi');

        $this->assertNotSame($versionBefore, Cache::get('rs_web_cms_api_news_version'));

        $audit1 = AuditLog::where('action', 'cache.invalidated_manually')->latest('id')->firstOrFail();
        $this->assertSame('news', $audit1->metadata['module']);
        $this->assertSame('unconfigured', $audit1->metadata['webhook_status']);

        // 2-4. Gunakan Http::fakeSequence agar urutan respons (200 -> 500 -> 200 -> 200) deterministik.
        Config::set('web_frontend.webhook_url', 'https://web.example.test/api/webhook');
        Config::set('web_frontend.cons_id', 'SUPER-SECRET-CONSID-999');
        Config::set('web_frontend.secret_key', 'SUPER-SECRET-KEY-999');

        Http::fakeSequence('https://web.example.test/*')
            ->push(['ok' => true], 200)
            ->push(['error' => 'down'], 500)
            ->push(['ok' => true], 200)
            ->push(['ok' => true], 200);

        Livewire::test(SystemMonitorPage::class)
            ->callAction('invalidateWebCache', ['module' => 'all'])
            ->assertNotified('Cache lokal dan Web Frontend berhasil diinvalidasi');

        $audit2 = AuditLog::where('action', 'cache.invalidated_manually')->latest('id')->firstOrFail();
        $this->assertSame('all', $audit2->metadata['module']);
        $this->assertSame('success', $audit2->metadata['webhook_status']);

        // 3. Outcome failed (HTTP 500 — no fake success)
        Livewire::test(SystemMonitorPage::class)
            ->callAction('invalidateWebCache', ['module' => 'articles'])
            ->assertNotified('Cache lokal dihapus, tetapi pengiriman webhook gagal');

        $audit3 = AuditLog::where('action', 'cache.invalidated_manually')->latest('id')->firstOrFail();
        $this->assertSame('articles', $audit3->metadata['module']);
        $this->assertSame('failed', $audit3->metadata['webhook_status']);

        // 4. Rate limiter: 2 aksi lagi (total 5) berhasil diproses, panggilan ke-6 diblokir
        Livewire::test(SystemMonitorPage::class)->callAction('invalidateWebCache', ['module' => 'news']);
        Livewire::test(SystemMonitorPage::class)->callAction('invalidateWebCache', ['module' => 'news']);

        $countBeforeRateLimit = AuditLog::where('action', 'cache.invalidated_manually')->count();

        Livewire::test(SystemMonitorPage::class)
            ->callAction('invalidateWebCache', ['module' => 'news'])
            ->assertNotified('Batas permintaan invalidasi tercapai');

        $this->assertSame($countBeforeRateLimit, AuditLog::where('action', 'cache.invalidated_manually')->count());
    }

    public function test_system_monitor_displays_telemetry_and_sanitized_failed_jobs_without_leaking_secrets_or_pii(): void
    {
        Config::set('web_frontend.webhook_url', 'https://web.example.test/api/webhook');
        Config::set('web_frontend.cons_id', 'SECRET-CONSID-NEVER-SHOW');
        Config::set('web_frontend.secret_key', 'SECRET-KEY-NEVER-SHOW');
        Config::set('rsapi.enabled', false);

        Http::fake(['https://web.example.test/*' => Http::response(['ok' => true], 200)]);
        app(WebFrontendWebhookService::class)->dispatch('promotions', 'content.updated');

        $apiMock = \Mockery::mock(DoctorApiService::class);
        (new DoctorSyncService($apiMock))->syncFromMedInfras();

        DB::table('failed_jobs')->insert([
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode([
                'displayName' => 'App\\Jobs\\SendCacheInvalidationWebhookJob',
                'data' => [
                    'command' => 'RAW-PAYLOAD-SECRET-PII-SHOULD-NEVER-APPEAR',
                ],
            ]),
            'exception' => "RuntimeException: Timeout for 081299998888 secret=TOPSECRET123 https://user:pass@host.test/hook?token=abc\n#0 /var/www/app.php(12)",
            'failed_at' => now(),
        ]);

        $this->actingAs($this->superAdmin);

        $response = $this->get('/admin/system-monitor')->assertOk();
        $response->assertSee('SendCacheInvalidationWebhookJob');
        $response->assertSee('11111111-2222-33');
        $response->assertSee('Promosi');
        $response->assertSee('Berhasil');
        $response->assertSee('Nonaktif');

        // Pastikan nol secret, nol raw payload, dan nol nomor telepon muncul di HTML
        $response->assertDontSee('SECRET-CONSID-NEVER-SHOW');
        $response->assertDontSee('SECRET-KEY-NEVER-SHOW');
        $response->assertDontSee('RAW-PAYLOAD-SECRET-PII-SHOULD-NEVER-APPEAR');
        $response->assertDontSee('081299998888');
        $response->assertDontSee('TOPSECRET123');
        $response->assertDontSee('user:pass@');

        // Verifikasi ApiCacheInvalidator::modules() lengkap 9 modul
        $this->assertCount(9, ApiCacheInvalidator::modules());

        // Verifikasi snapshot cache terekam via IntegrationTelemetryService
        $telemetry = app(IntegrationTelemetryService::class);
        $this->assertSame('success', $telemetry->getLastWebhookSnapshot()['status'] ?? null);
        $this->assertSame('disabled', $telemetry->getLastDoctorSyncSnapshot()['status'] ?? null);
    }
}
