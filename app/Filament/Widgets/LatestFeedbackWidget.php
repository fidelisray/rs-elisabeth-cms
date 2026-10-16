<?php

namespace App\Filament\Widgets;

use App\Services\FeedbackService;
use Filament\Widgets\Widget;

class LatestFeedbackWidget extends Widget
{
    protected string $view = 'filament.widgets.latest-feedback-widget';

    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        /** @var FeedbackService $feedbackService */
        $feedbackService = app(FeedbackService::class);

        return [
            'feedbackList' => $feedbackService->getLatestFeedback(10),
        ];
    }
}
