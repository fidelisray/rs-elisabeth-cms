<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\DynamicFormStatus;
use App\Filament\Resources\DynamicForms\DynamicFormResource;
use App\Filament\Resources\DynamicFormSubmissions\DynamicFormSubmissionResource;
use App\Filament\Resources\Feedbacks\FeedbackResource;
use App\Filament\Resources\Questionnaires\QuestionnaireResource;
use App\Models\DynamicForm;
use App\Models\DynamicFormSubmission;
use App\Models\Feedback;
use App\Models\QuestionnaireResponse;
use App\Services\FeedbackService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Tabbed Executive Patient Voice Workspace untuk Dashboard (/admin).
 *
 * Menggabungkan Kritik & Saran, Kuesioner Kepuasan, dan Formulir Kustom
 * dalam 1 workspace ber-tab ringkas (maks 5 entri terbaru, nol PII pasien).
 * Visibilitas setiap tab mengikuti policy resource masing-masing secara otomatis.
 */
class PatientVoiceWorkspaceWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.patient-voice-workspace-widget';

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Gate::allows('viewAny', Feedback::class)
            || Gate::allows('viewAny', QuestionnaireResponse::class)
            || Gate::allows('viewAny', DynamicForm::class)
            || Gate::allows('viewAny', DynamicFormSubmission::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $canViewFeedback = Gate::allows('viewAny', Feedback::class);
        $canViewQuestionnaires = Gate::allows('viewAny', QuestionnaireResponse::class);
        $canViewDynamicForms = Gate::allows('viewAny', DynamicForm::class);
        $canViewDynamicSubmissions = Gate::allows('viewAny', DynamicFormSubmission::class);
        $canViewDynamicTab = $canViewDynamicForms || $canViewDynamicSubmissions;

        /** @var FeedbackService $feedbackService */
        $feedbackService = app(FeedbackService::class);

        $tabs = [];
        $feedbackData = null;
        $questionnaireData = null;
        $dynamicData = null;

        if ($canViewFeedback) {
            $summary = $feedbackService->getFeedbackSummary();
            $latest = $feedbackService->getLatestFeedback(5)->map(static function (Feedback $item): array {
                $categories = array_values(array_filter(
                    (array) ($item->categories ?? []),
                    static fn ($cat): bool => is_string($cat) && trim($cat) !== '',
                ));

                return [
                    'ticket' => $item->ticket_number,
                    'categories' => $categories !== [] ? implode(', ', $categories) : '—',
                    'detail' => Str::limit(trim((string) $item->complaint_detail), 120, '...'),
                    'status' => $item->status,
                    'status_label' => FeedbackResource::statusLabel($item->status),
                    'submitted' => optional($item->submitted_at)->format('d M Y, H:i') ?? '-',
                    'url' => FeedbackResource::getUrl('view', ['record' => $item]),
                ];
            })->all();

            $unresolvedCount = (int) ($summary['baru'] ?? 0) + (int) ($summary['diproses'] ?? 0);

            $tabs[] = [
                'key' => 'feedback',
                'label' => __('Kritik & Saran'),
                'count' => $unresolvedCount > 0 ? $unresolvedCount : (int) ($summary['total'] ?? 0),
                'count_label' => $unresolvedCount > 0
                    ? __(':count terbuka', ['count' => $unresolvedCount])
                    : __(':count total', ['count' => (int) ($summary['total'] ?? 0)]),
            ];

            $feedbackData = [
                'summary' => $summary,
                'latest' => $latest,
                'index_url' => FeedbackResource::getUrl('index'),
            ];
        }

        if ($canViewQuestionnaires) {
            $units = [];
            $totalResponses = 0;

            foreach (QuestionnaireResponse::TYPES as $type => $label) {
                $summary = $feedbackService->getQuestionnaireSummary($type);
                $aspects = $feedbackService->getQuestionnaireAspectBreakdown($type);
                $unitTotal = (int) ($summary['total'] ?? 0);
                $totalResponses += $unitTotal;

                $units[] = [
                    'type' => $type,
                    'label' => $label,
                    'total' => $unitTotal,
                    'satisfaction' => $feedbackService->getSatisfactionPercent($type),
                    'highest' => $aspects['highest'],
                    'lowest' => $aspects['lowest'],
                    'latest' => $feedbackService->getLatestQuestionnaires($type, 5)->map(static function (QuestionnaireResponse $q) use ($feedbackService): array {
                        $score = $feedbackService->calculateResponseScore((array) ($q->ratings ?? []));

                        return [
                            'score_percent' => $score['percent'],
                            'payment' => $q->payment_method ?: '-',
                            'age' => $q->age_group ?: '-',
                            'suggestions' => filled($q->suggestions) ? Str::limit(trim((string) $q->suggestions), 100, '...') : '—',
                            'submitted' => optional($q->submitted_at)->format('d M Y, H:i') ?? '-',
                            'url' => QuestionnaireResource::getUrl('view', ['record' => $q]),
                        ];
                    })->all(),
                ];
            }

            $tabs[] = [
                'key' => 'questionnaires',
                'label' => __('Kuesioner Kepuasan'),
                'count' => $totalResponses,
                'count_label' => __(':count respons', ['count' => $totalResponses]),
            ];

            $questionnaireData = [
                'total' => $totalResponses,
                'units' => $units,
                'index_url' => QuestionnaireResource::getUrl('index'),
            ];
        }

        if ($canViewDynamicTab) {
            $activeFormsCount = 0;
            $activeForms = [];

            if ($canViewDynamicForms) {
                $activeFormsQuery = DynamicForm::query()
                    ->where('status', DynamicFormStatus::Published->value)
                    ->whereNotNull('published_version_id');

                $activeFormsCount = (clone $activeFormsQuery)->count();
                $activeForms = (clone $activeFormsQuery)
                    ->withCount('submissions')
                    ->orderByDesc('updated_at')
                    ->limit(5)
                    ->get()
                    ->map(static fn (DynamicForm $form): array => [
                        'title' => $form->title,
                        'mode' => $form->mode->label(),
                        'submissions_count' => (int) $form->submissions_count,
                        'analytics_url' => DynamicFormResource::getUrl('analytics', ['record' => $form]),
                    ])->all();
            }

            $totalSubmissions = 0;
            $latestSubmissions = [];

            if ($canViewDynamicSubmissions) {
                $submissionQuery = DynamicFormSubmission::query()
                    ->whereHas('form', static fn ($q) => $q->whereNull('dynamic_forms.deleted_at'));

                $totalSubmissions = (clone $submissionQuery)->count();
                $latestSubmissions = (clone $submissionQuery)
                    ->with('form:id,title,mode')
                    ->orderByDesc('submitted_at')
                    ->limit(5)
                    ->get()
                    ->map(static fn (DynamicFormSubmission $sub): array => [
                        'response_label' => __('Respons #:id', ['id' => $sub->id]),
                        'form_title' => $sub->form?->title ?? '-',
                        'mode_label' => $sub->form?->mode?->label() ?? '-',
                        'submitted_at' => optional($sub->submitted_at)->format('d M Y, H:i') ?? '-',
                        'view_url' => DynamicFormSubmissionResource::getUrl('view', ['record' => $sub]),
                    ])->all();
            }

            $tabs[] = [
                'key' => 'dynamic_forms',
                'label' => __('Formulir Kustom'),
                'count' => $totalSubmissions,
                'count_label' => __(':count respons', ['count' => $totalSubmissions]),
            ];

            $dynamicData = [
                'can_view_forms' => $canViewDynamicForms,
                'can_view_submissions' => $canViewDynamicSubmissions,
                'active_forms_count' => $activeFormsCount,
                'total_submissions_count' => $totalSubmissions,
                'active_forms' => $activeForms,
                'latest_submissions' => $latestSubmissions,
                'forms_index_url' => $canViewDynamicForms ? DynamicFormResource::getUrl('index') : null,
                'submissions_index_url' => $canViewDynamicSubmissions ? DynamicFormSubmissionResource::getUrl('index') : null,
            ];
        }

        return [
            'tabs' => $tabs,
            'defaultTab' => $tabs[0]['key'] ?? null,
            'feedbackData' => $feedbackData,
            'questionnaireData' => $questionnaireData,
            'dynamicData' => $dynamicData,
        ];
    }
}
