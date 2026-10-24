<?php

namespace App\Filament\Widgets;

use App\Models\QuestionnaireResponse;
use App\Services\FeedbackService;
use Filament\Widgets\Widget;

/**
 * Section "Kuesioner Kepuasan": tiga sub-section (IGD, Rawat Jalan, Rawat Inap).
 */
class QuestionnaireSectionWidget extends Widget
{
    protected string $view = 'filament.widgets.questionnaire-section-widget';

    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        /** @var FeedbackService $service */
        $service = app(FeedbackService::class);

        $groups = [];

        foreach (QuestionnaireResponse::TYPES as $type => $label) {
            $summary = $service->getQuestionnaireSummary($type);

            $groups[] = [
                'type' => $type,
                'label' => $label,
                'total' => $summary['total'],
                'satisfaction' => $service->getSatisfactionPercent($type),
                'by_age' => $summary['by_age'],
                'latest' => $service->getLatestQuestionnaires($type, 5)->map(fn ($q) => [
                    'age' => $q->age_group,
                    'gender' => $q->gender,
                    'payment' => $q->payment_method,
                    'submitted' => optional($q->submitted_at)->format('d M Y, H:i'),
                ])->all(),
            ];
        }

        return ['groups' => $groups];
    }
}
