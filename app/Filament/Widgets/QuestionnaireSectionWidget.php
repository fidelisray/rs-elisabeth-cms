<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Questionnaires\QuestionnaireResource;
use App\Models\QuestionnaireResponse;
use App\Services\FeedbackService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;

/**
 * Section "Kuesioner Kepuasan": tiga sub-section (IGD, Rawat Jalan, Rawat Inap).
 */
class QuestionnaireSectionWidget extends Widget
{
    protected string $view = 'filament.widgets.questionnaire-section-widget';

    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Gate::allows('viewAny', QuestionnaireResponse::class);
    }

    public function getViewData(): array
    {
        Gate::authorize('viewAny', QuestionnaireResponse::class);
        /** @var FeedbackService $service */
        $service = app(FeedbackService::class);

        $groups = [];

        foreach (QuestionnaireResponse::TYPES as $type => $label) {
            $summary = $service->getQuestionnaireSummary($type);
            $aspects = $service->getQuestionnaireAspectBreakdown($type);

            $groups[] = [
                'type' => $type,
                'label' => $label,
                'total' => $summary['total'],
                'satisfaction' => $service->getSatisfactionPercent($type),
                'by_age' => $summary['by_age'],
                'by_payment' => $summary['by_payment'],
                'by_gender' => $summary['by_gender'],
                'aspects' => $aspects,
                'latest' => $service->getLatestQuestionnaires($type, 5)->map(function ($q) use ($service): array {
                    $score = $service->calculateResponseScore((array) ($q->ratings ?? []));

                    return [
                        'age' => $q->age_group,
                        'gender' => $q->gender,
                        'payment' => $q->payment_method,
                        'suggestions' => $q->suggestions,
                        'score_percent' => $score['percent'],
                        'score_positive' => $score['positive'],
                        'score_total' => $score['total'],
                        'submitted' => optional($q->submitted_at)->format('d M Y, H:i'),
                        'submitted_human' => optional($q->submitted_at)->diffForHumans(),
                        'url' => QuestionnaireResource::getUrl('view', ['record' => $q]),
                    ];
                })->all(),
            ];
        }

        return ['groups' => $groups];
    }
}
