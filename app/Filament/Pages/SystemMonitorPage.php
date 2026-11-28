<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\AdminNavigationGroup;
use App\Models\AuditLog;
use App\Services\IntegrationTelemetryService;
use App\Services\WebFrontendWebhookService;
use App\Support\ApiCacheInvalidator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use UnitEnum;

class SystemMonitorPage extends Page
{
    private const INVALIDATE_RATE_LIMIT_MAX = 5;

    private const INVALIDATE_RATE_LIMIT_DECAY_SECONDS = 60;

    protected string $view = 'filament.pages.system-monitor-page';

    protected static ?string $slug = 'system-monitor';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::SystemOperations;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('Sistem & Integrasi');
    }

    public function getTitle(): string
    {
        return __('Sistem & Integrasi');
    }

    public function getHeading(): string
    {
        return __('Sistem & Integrasi');
    }

    public function getSubheading(): ?string
    {
        return __('Pantau status webhook ke website publik, sinkronisasi SIMRS MedInfras, dan antrean sistem.');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user !== null && ($user->isSuperAdmin() || $user->can('View:SystemMonitor'));
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canTriggerCacheInvalidation(): bool
    {
        $user = Auth::user();

        return $user !== null && ($user->isSuperAdmin() || $user->can('Trigger:WebCacheInvalidation'));
    }

    /**
     * @return array<string, string>
     */
    public static function moduleOptions(): array
    {
        return [
            'all' => __('Semua Modul'),
            'news' => __('Berita'),
            'articles' => __('Artikel'),
            'promotions' => __('Promosi'),
            'banner_promotions' => __('Banner Promosi'),
            'facilities' => __('Fasilitas & Layanan'),
            'room_facilities' => __('Kamar Rawat Inap'),
            'doctors' => __('Profil Dokter'),
            'online_libraries' => __('Perpustakaan Online'),
            'dynamic_forms' => __('Formulir Kuesioner Kustom'),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshMonitor')
                ->label(__('Muat Ulang Status'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(fn () => null),

            Action::make('invalidateWebCache')
                ->label(__('Invalidasi Cache Web'))
                ->icon(Heroicon::OutlinedBolt)
                ->color('primary')
                ->visible(fn (): bool => static::canTriggerCacheInvalidation())
                ->authorize(fn (): bool => static::canTriggerCacheInvalidation())
                ->requiresConfirmation()
                ->modalHeading(__('Konfirmasi Invalidasi Cache Web'))
                ->modalDescription(__('Tindakan ini menghapus cache API CMS lokal dan mengirim sinyal webhook pembatalan cache ke website publik rs-elisabeth-web. Seluruh eksekusi dicatat ke log audit.'))
                ->modalSubmitActionLabel(__('Jalankan Invalidasi'))
                ->schema([
                    Select::make('module')
                        ->label(__('Target Modul'))
                        ->options(fn (): array => static::moduleOptions())
                        ->default('all')
                        ->required()
                        ->native(false),
                ])
                ->action(function (array $data): void {
                    $this->executeManualCacheInvalidation((string) ($data['module'] ?? 'all'));
                }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var IntegrationTelemetryService $telemetry */
        $telemetry = app(IntegrationTelemetryService::class);

        $flags = $telemetry->getSystemConfigFlags();
        $webhookLast = $telemetry->getLastWebhookSnapshot();
        $doctorSyncLast = $telemetry->getLastDoctorSyncSnapshot();
        $failedJobs = $telemetry->getFailedJobsSummary(10);

        return [
            'flags' => $flags,
            'webhookLast' => $webhookLast,
            'doctorSyncLast' => $doctorSyncLast,
            'failedJobs' => $failedJobs,
            'canTriggerCacheInvalidation' => static::canTriggerCacheInvalidation(),
            'webhookLastAt' => $this->formatRecordedAt($webhookLast['recorded_at'] ?? null),
            'doctorSyncLastAt' => $this->formatRecordedAt($doctorSyncLast['recorded_at'] ?? null),
            'moduleLabels' => static::moduleOptions(),
        ];
    }

    private function executeManualCacheInvalidation(string $module): void
    {
        if (! static::canTriggerCacheInvalidation()) {
            abort(403);
        }

        $allowedModules = ['all', ...ApiCacheInvalidator::modules()];
        if (! in_array($module, $allowedModules, true)) {
            Notification::make()
                ->danger()
                ->title(__('Modul cache tidak dikenal'))
                ->send();

            return;
        }

        $rateKey = 'system-monitor:invalidate-cache:'.(string) Auth::id();
        if (RateLimiter::tooManyAttempts($rateKey, self::INVALIDATE_RATE_LIMIT_MAX)) {
            $seconds = RateLimiter::availableIn($rateKey);

            Notification::make()
                ->warning()
                ->title(__('Batas permintaan invalidasi tercapai'))
                ->body(__('Silakan tunggu :seconds detik sebelum menjalankan invalidasi cache kembali.', ['seconds' => $seconds]))
                ->send();

            return;
        }

        RateLimiter::hit($rateKey, self::INVALIDATE_RATE_LIMIT_DECAY_SECONDS);

        if ($module === 'all') {
            ApiCacheInvalidator::forgetAllModules();
        } else {
            ApiCacheInvalidator::forgetForModule($module);
        }

        $webhookStatus = 'failed';
        $errorSummary = null;

        try {
            $dispatched = app(WebFrontendWebhookService::class)->dispatch($module, 'content.updated');
            $webhookStatus = $dispatched ? 'success' : 'unconfigured';
        } catch (\Throwable $e) {
            $webhookStatus = 'failed';
            $errorSummary = IntegrationTelemetryService::sanitizeMessage($e->getMessage());
        }

        AuditLog::record('cache.invalidated_manually', null, array_filter([
            'module' => $module,
            'webhook_status' => $webhookStatus,
            'source' => 'system_monitor',
        ], static fn ($value): bool => $value !== null));

        $moduleLabel = static::moduleOptions()[$module] ?? $module;

        if ($webhookStatus === 'success') {
            Notification::make()
                ->success()
                ->title(__('Cache lokal dan Web Frontend berhasil diinvalidasi'))
                ->body(__('Target modul: :module. Sinyal webhook diterima dengan status 200 OK.', ['module' => $moduleLabel]))
                ->send();

            return;
        }

        if ($webhookStatus === 'unconfigured') {
            Notification::make()
                ->warning()
                ->title(__('Cache lokal dihapus, tetapi webhook belum dikonfigurasi'))
                ->body(__('Target modul: :module. Konfigurasi webhook website publik belum lengkap sehingga sinyal keluar dilewati.', ['module' => $moduleLabel]))
                ->send();

            return;
        }

        Notification::make()
            ->danger()
            ->title(__('Cache lokal dihapus, tetapi pengiriman webhook gagal'))
            ->body(__('Target modul: :module. Ringkasan error: :error', [
                'module' => $moduleLabel,
                'error' => $errorSummary ?? '-',
            ]))
            ->send();
    }

    private function formatRecordedAt(mixed $isoTimestamp): ?string
    {
        if (! is_string($isoTimestamp) || trim($isoTimestamp) === '') {
            return null;
        }

        try {
            $carbon = Carbon::parse($isoTimestamp)->timezone((string) config('app.timezone', 'Asia/Jakarta'));

            return $carbon->translatedFormat('d M Y, H:i:s').' ('.$carbon->diffForHumans().')';
        } catch (\Throwable) {
            return null;
        }
    }
}
