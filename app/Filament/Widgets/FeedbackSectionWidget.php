<?php

namespace App\Filament\Widgets;

use App\Services\FeedbackService;
use Filament\Widgets\Widget;

/**
 * Section "Kritik & Saran": ringkasan status dan daftar terbaru dari database.
 * Nama dan nomor telepon pasien tidak ditampilkan di dashboard.
 */
class FeedbackSectionWidget extends Widget
{
    protected string $view = 'filament.widgets.feedback-section-widget';

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        /** @var FeedbackService $service */
        $service = app(FeedbackService::class);

        return [
            'summary' => $service->getFeedbackSummary(),
            'latest' => $service->getLatestFeedback(10)->map(fn ($f) => [
                'ticket' => $f->ticket_number,
                'categories' => implode(', ', $f->categories ?? []),
                'detail' => $f->complaint_detail,
                'status' => $f->status,
                'submitted' => optional($f->submitted_at)->format('d M Y, H:i'),
            ])->all(),
        ];
    }
}
