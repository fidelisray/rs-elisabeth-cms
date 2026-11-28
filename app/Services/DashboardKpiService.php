<?php

declare(strict_types=1);

namespace App\Services;

use App\Filament\Pages\SystemMonitorPage;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\BannerPromotions\BannerPromotionResource;
use App\Filament\Resources\Doctors\DoctorResource;
use App\Filament\Resources\FacilityServices\FacilityServiceResource;
use App\Filament\Resources\Feedbacks\FeedbackResource;
use App\Filament\Resources\News\NewsResource;
use App\Filament\Resources\OnlineLibraries\OnlineLibraryResource;
use App\Filament\Resources\Promotions\PromotionResource;
use App\Filament\Resources\RoomFacilities\RoomFacilityResource;
use App\Models\Article;
use App\Models\BannerPromotion;
use App\Models\Doctor;
use App\Models\FacilityService;
use App\Models\Feedback;
use App\Models\News;
use App\Models\OnlineLibrary;
use App\Models\Promotion;
use App\Models\RoomFacility;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Menghitung 5 metrik KPI operasional Dashboard secara policy-driven,
 * lintas driver (SQLite & PostgreSQL 15), dan bebas PII pasien.
 */
class DashboardKpiService
{
    public function __construct(
        protected FeedbackService $feedbackService,
        protected IntegrationTelemetryService $telemetryService,
    ) {}

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     value: string,
     *     secondary: string,
     *     badge: string,
     *     tone: string,
     *     url: string,
     *     cta: string
     * }>
     */
    public function buildForUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $gate = Gate::forUser($user);
        $cards = [];

        $contentCard = $this->buildContentCard($gate);
        if ($contentCard !== null) {
            $cards[] = $contentCard;
        }

        if ($gate->allows('viewAny', Promotion::class)) {
            $cards[] = $this->buildPromotionsCard();
        }

        if ($gate->allows('viewAny', Doctor::class)) {
            $cards[] = $this->buildDoctorsCard();
        }

        if ($gate->allows('viewAny', Feedback::class)) {
            $cards[] = $this->buildFeedbackCard();
        }

        $canViewSystemMonitor = $user->isSuperAdmin() || $user->can('View:SystemMonitor');
        if ($canViewSystemMonitor || $gate->allows('viewAny', Doctor::class)) {
            $cards[] = $this->buildSystemHealthCard($canViewSystemMonitor);
        }

        return $cards;
    }

    /**
     * @return array{key: string, label: string, value: string, secondary: string, badge: string, tone: string, url: string, cta: string}|null
     */
    private function buildContentCard(\Illuminate\Contracts\Auth\Access\Gate $gate): ?array
    {
        $modules = [
            [
                'model' => News::class,
                'resource' => NewsResource::class,
                'filter' => 'is_published',
                'published' => fn (): int => News::query()->where('is_published', true)->count(),
                'draft' => fn (): int => News::query()->where('is_published', false)->count(),
            ],
            [
                'model' => Article::class,
                'resource' => ArticleResource::class,
                'filter' => 'is_active',
                // Kolom articles.is_active tersimpan sebagai string 'yes'/'no' (bukan boolean).
                'published' => fn (): int => Article::query()->where('is_active', 'yes')->count(),
                'draft' => fn (): int => Article::query()->where('is_active', '!=', 'yes')->count(),
            ],
            [
                'model' => Promotion::class,
                'resource' => PromotionResource::class,
                'filter' => 'is_active',
                'published' => fn (): int => Promotion::query()->where('is_active', true)->count(),
                'draft' => fn (): int => Promotion::query()->where('is_active', false)->count(),
            ],
            [
                'model' => BannerPromotion::class,
                'resource' => BannerPromotionResource::class,
                'filter' => 'is_active',
                'published' => fn (): int => BannerPromotion::query()->where('is_active', true)->count(),
                'draft' => fn (): int => BannerPromotion::query()->where('is_active', false)->count(),
            ],
            [
                'model' => FacilityService::class,
                'resource' => FacilityServiceResource::class,
                'filter' => 'is_active',
                'published' => fn (): int => FacilityService::query()->where('is_active', true)->count(),
                'draft' => fn (): int => FacilityService::query()->where('is_active', false)->count(),
            ],
            [
                'model' => RoomFacility::class,
                'resource' => RoomFacilityResource::class,
                'filter' => 'is_active',
                'published' => fn (): int => RoomFacility::query()->where('is_active', true)->count(),
                'draft' => fn (): int => RoomFacility::query()->where('is_active', false)->count(),
            ],
            [
                'model' => OnlineLibrary::class,
                'resource' => OnlineLibraryResource::class,
                'filter' => 'is_published',
                'published' => fn (): int => OnlineLibrary::query()->where('is_published', true)->count(),
                'draft' => fn (): int => OnlineLibrary::query()->where('is_published', false)->count(),
            ],
        ];

        $allowedModules = 0;
        $totalPublished = 0;
        $totalDraft = 0;
        $firstDraftUrl = null;
        $fallbackUrl = null;

        foreach ($modules as $module) {
            if (! $gate->allows('viewAny', $module['model'])) {
                continue;
            }

            $allowedModules++;
            /** @var class-string<\Filament\Resources\Resource> $resourceClass */
            $resourceClass = $module['resource'];

            if ($fallbackUrl === null) {
                $fallbackUrl = $resourceClass::getUrl('index');
            }

            $published = ($module['published'])();
            $draft = ($module['draft'])();

            $totalPublished += $published;
            $totalDraft += $draft;

            if ($draft > 0 && $firstDraftUrl === null) {
                $firstDraftUrl = $resourceClass::getUrl('index', [
                    'filters' => [
                        $module['filter'] => ['value' => '0'],
                    ],
                ]);
            }
        }

        if ($allowedModules === 0) {
            return null;
        }

        return [
            'key' => 'content_overview',
            'label' => __('Konten Publikasi & Draf'),
            'value' => (string) $totalPublished,
            'secondary' => __(':draft draf di :modules modul', ['draft' => $totalDraft, 'modules' => $allowedModules]),
            'badge' => $totalDraft > 0 ? __(':count Draf', ['count' => $totalDraft]) : __('Semua Tayang'),
            'tone' => $totalDraft > 0 ? 'warning' : 'success',
            'url' => $firstDraftUrl ?? (string) $fallbackUrl,
            'cta' => $totalDraft > 0 ? __('Tinjau Draf') : __('Kelola Konten'),
        ];
    }

    /**
     * @return array{key: string, label: string, value: string, secondary: string, badge: string, tone: string, url: string, cta: string}
     */
    private function buildPromotionsCard(): array
    {
        $today = Promotion::today();
        $soonDate = Carbon::parse($today, (string) config('app.timezone', 'Asia/Jakarta'))
            ->addDays(7)
            ->toDateString();

        $activeCount = Promotion::query()->activeOn($today)->count();
        $expiringSoonCount = Promotion::query()
            ->activeOn($today)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<=', $soonDate)
            ->count();

        $url = $expiringSoonCount > 0
            ? PromotionResource::getUrl('index', ['filters' => ['expiring_soon' => ['isActive' => true]]])
            : PromotionResource::getUrl('index');

        return [
            'key' => 'promotions',
            'label' => __('Promosi Aktif'),
            'value' => (string) $activeCount,
            'secondary' => $expiringSoonCount > 0
                ? __(':count promo berakhir ≤ 7 hari', ['count' => $expiringSoonCount])
                : __('Tidak ada yang segera berakhir'),
            'badge' => $expiringSoonCount > 0 ? __('≤ 7 Hari: :count', ['count' => $expiringSoonCount]) : __('Aman'),
            'tone' => $expiringSoonCount > 0 ? 'warning' : 'success',
            'url' => $url,
            'cta' => $expiringSoonCount > 0 ? __('Cek Promo Berakhir') : __('Lihat Promosi'),
        ];
    }

    /**
     * @return array{key: string, label: string, value: string, secondary: string, badge: string, tone: string, url: string, cta: string}
     */
    private function buildDoctorsCard(): array
    {
        $activeDoctors = Doctor::query()
            ->where('is_active', true)
            ->select(['id', 'bio', 'location', 'experience', 'education'])
            ->get();

        $totalActive = $activeDoctors->count();
        $completeCount = $activeDoctors->filter(fn (Doctor $doctor): bool => $doctor->is_profile_complete)->count();
        $incompleteCount = $totalActive - $completeCount;
        $percent = $totalActive > 0 ? (int) round(($completeCount / $totalActive) * 100) : 0;

        $url = $incompleteCount > 0
            ? DoctorResource::getUrl('index', ['filters' => ['profile_status' => ['value' => '0']]])
            : DoctorResource::getUrl('index');

        $tone = $totalActive === 0 ? 'neutral' : ($percent >= 90 ? 'success' : ($percent >= 50 ? 'warning' : 'danger'));

        return [
            'key' => 'doctors',
            'label' => __('Profil Dokter Bilingual'),
            'value' => $percent.'%',
            'secondary' => __(':complete/:total dokter aktif lengkap', ['complete' => $completeCount, 'total' => $totalActive]),
            'badge' => $incompleteCount > 0 ? __(':count Belum Lengkap', ['count' => $incompleteCount]) : __('Lengkap'),
            'tone' => $tone,
            'url' => $url,
            'cta' => $incompleteCount > 0 ? __('Lengkapi Profil') : __('Lihat Dokter'),
        ];
    }

    /**
     * @return array{key: string, label: string, value: string, secondary: string, badge: string, tone: string, url: string, cta: string}
     */
    private function buildFeedbackCard(): array
    {
        $summary = $this->feedbackService->getFeedbackSummary();
        $baru = (int) ($summary['baru'] ?? 0);
        $diproses = (int) ($summary['diproses'] ?? 0);
        $unresolved = $baru + $diproses;

        $url = $unresolved > 0
            ? FeedbackResource::getUrl('index', ['filters' => ['unresolved' => ['isActive' => true]]])
            : FeedbackResource::getUrl('index');

        return [
            'key' => 'feedback',
            'label' => __('Kritik & Saran Terbuka'),
            'value' => (string) $unresolved,
            'secondary' => __(':new baru · :in_progress diproses', ['new' => $baru, 'in_progress' => $diproses]),
            'badge' => $unresolved > 0 ? __('Perlu Tindak Lanjut') : __('Semua Selesai'),
            'tone' => $baru > 0 ? 'warning' : ($unresolved > 0 ? 'info' : 'success'),
            'url' => $url,
            'cta' => $unresolved > 0 ? __('Tinjau Tiket') : __('Lihat Semua'),
        ];
    }

    /**
     * @return array{key: string, label: string, value: string, secondary: string, badge: string, tone: string, url: string, cta: string}
     */
    private function buildSystemHealthCard(bool $canViewSystemMonitor): array
    {
        $failedJobsCount = $this->telemetryService->getFailedJobsCount();
        $rsApiEnabled = (bool) config('rsapi.enabled', true);
        $webhookLast = $this->telemetryService->getLastWebhookSnapshot();
        $webhookFailed = ($webhookLast['status'] ?? null) === 'failed';

        $activeDoctorsCount = Doctor::query()->where('is_active', true)->count();

        if ($failedJobsCount > 0 || $webhookFailed) {
            $value = __(':count Gagal', ['count' => max($failedJobsCount, $webhookFailed ? 1 : 0)]);
            $badge = __('Perlu Perhatian');
            $tone = 'danger';
        } else {
            $value = $rsApiEnabled ? __('SIMRS Aktif') : __('SIMRS Offline');
            $badge = __('0 Antrean Gagal');
            $tone = $rsApiEnabled ? 'success' : 'neutral';
        }

        $url = $canViewSystemMonitor
            ? SystemMonitorPage::getUrl()
            : DoctorResource::getUrl('index');

        return [
            'key' => 'system_health',
            'label' => __('SIMRS & Antrean Sistem'),
            'value' => $value,
            'secondary' => __(':doctors dokter aktif · :failed antrean gagal', [
                'doctors' => $activeDoctorsCount,
                'failed' => $failedJobsCount,
            ]),
            'badge' => $badge,
            'tone' => $tone,
            'url' => $url,
            'cta' => $canViewSystemMonitor ? __('Buka Monitor Sistem') : __('Lihat Dokter'),
        ];
    }
}
