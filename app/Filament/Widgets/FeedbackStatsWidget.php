<?php

namespace App\Filament\Widgets;

use App\Services\FeedbackService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FeedbackStatsWidget extends BaseWidget
{
    protected static ?int $sort = 10;

    protected function getStats(): array
    {
        /** @var FeedbackService $feedbackService */
        $feedbackService = app(FeedbackService::class);
        $stats = $feedbackService->getStats();

        $averageRating = $stats['average_rating'] ?? 0;
        $totalReviews = $stats['total_reviews'] ?? 0;
        $satisfaction = $stats['satisfaction_percent'] ?? 0;

        // Render bintang untuk rata-rata rating
        $fullStars = (int) floor($averageRating);
        $halfStar = ($averageRating - $fullStars) >= 0.5 ? 1 : 0;
        $emptyStars = 5 - $fullStars - $halfStar;
        $starDisplay = str_repeat('★', $fullStars)
                     .str_repeat('½', $halfStar)
                     .str_repeat('☆', $emptyStars);

        // Tentukan warna berdasarkan persentase kepuasan
        $satisfactionColor = match (true) {
            $satisfaction >= 80 => 'success',
            $satisfaction >= 60 => 'warning',
            default => 'danger',
        };

        return [
            Stat::make('Rata-rata Rating', number_format($averageRating, 1).' / 5.0')
                ->description($starDisplay.' dari pasien kami')
                ->descriptionIcon('heroicon-m-star')
                ->color('warning')
                ->icon('heroicon-o-star'),

            Stat::make('Total Ulasan Masuk', number_format($totalReviews).' ulasan')
                ->description('Semua ulasan yang diterima')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('info')
                ->icon('heroicon-o-chat-bubble-left-right'),

            Stat::make('Tingkat Kepuasan', $satisfaction.'%')
                ->description('Pasien memberi rating ≥ 4 bintang')
                ->descriptionIcon('heroicon-m-face-smile')
                ->color($satisfactionColor)
                ->icon('heroicon-o-heart'),
        ];
    }
}
