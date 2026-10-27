<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Feedbacks\FeedbackResource;
use App\Models\Feedback;
use App\Services\FeedbackService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;

/**
 * Section "Kritik & Saran": ringkasan status dan daftar terbaru dari database.
 * Nama dan nomor telepon pasien tidak ditampilkan di dashboard.
 */
class FeedbackSectionWidget extends Widget
{
    protected string $view = 'filament.widgets.feedback-section-widget';

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Gate::allows('viewAny', Feedback::class);
    }

    public function getViewData(): array
    {
        Gate::authorize('viewAny', Feedback::class);
        /** @var FeedbackService $service */
        $service = app(FeedbackService::class);

        return [
            'summary' => $service->getFeedbackSummary(),
            'latest' => $service->getLatestFeedback(10)->map(function ($f): array {
                $categoryList = array_values(array_filter((array) ($f->categories ?? []), fn ($item) => is_string($item) && trim($item) !== ''));

                return [
                    'ticket' => $f->ticket_number,
                    'categories' => implode(', ', $categoryList),
                    'category_list' => $categoryList,
                    'detail' => $f->complaint_detail,
                    'status' => $f->status,
                    'submitted' => optional($f->submitted_at)->format('d M Y, H:i'),
                    'submitted_human' => optional($f->submitted_at)->diffForHumans(),
                    'url' => FeedbackResource::getUrl('view', ['record' => $f]),
                ];
            })->all(),
        ];
    }
}
