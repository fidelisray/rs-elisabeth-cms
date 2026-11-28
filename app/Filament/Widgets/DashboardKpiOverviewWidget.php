<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\DashboardKpiService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * Strip 5 Kartu KPI Actionable pada baris paling atas Dashboard.
 * Visibilitas setiap kartu otomatis mengikuti policy resource terkait (nol PII pasien).
 */
class DashboardKpiOverviewWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.dashboard-kpi-overview-widget';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return app(DashboardKpiService::class)->buildForUser($user) !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        return [
            'cards' => app(DashboardKpiService::class)->buildForUser(Auth::user()),
        ];
    }
}
