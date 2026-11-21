<?php

namespace App\Filament\Widgets;

use App\Enums\DynamicFormStatus;
use App\Filament\Resources\DynamicForms\DynamicFormResource;
use App\Filament\Resources\DynamicFormSubmissions\DynamicFormSubmissionResource;
use App\Models\DynamicForm;
use App\Models\DynamicFormSubmission;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Widget ringkasan Custom Questionnaire Form aktif dan respons terbaru.
 */
class DynamicFeedbackOverviewWidget extends Widget
{
    protected string $view = 'filament.widgets.dynamic-feedback-overview-widget';

    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = Auth::user();
        if (! $user || ! $user->can('View:DynamicFeedbackOverviewWidget')) {
            return false;
        }

        return Gate::allows('viewAny', DynamicForm::class) || Gate::allows('viewAny', DynamicFormSubmission::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $canViewForms = Gate::allows('viewAny', DynamicForm::class);
        $canViewSubmissions = Gate::allows('viewAny', DynamicFormSubmission::class);

        $activeFormsCount = 0;
        $activeFormsPayload = [];
        if ($canViewForms) {
            $activeFormsQuery = DynamicForm::query()
                ->where('status', DynamicFormStatus::Published->value)
                ->whereNotNull('published_version_id');

            $activeFormsCount = (clone $activeFormsQuery)->count();
            $activeFormsPayload = (clone $activeFormsQuery)
                ->withCount('submissions')
                ->orderByDesc('updated_at')
                ->limit(5)
                ->get()
                ->map(fn (DynamicForm $f): array => [
                    'title' => $f->title,
                    'mode' => $f->mode->label(),
                    'submissions_count' => $f->submissions_count,
                    'analytics_url' => DynamicFormResource::getUrl('analytics', ['record' => $f]),
                ])->all();
        }

        $totalSubmissionsCount = 0;
        $latestSubmissionsPayload = [];
        if ($canViewSubmissions) {
            $baseSubmissionQuery = DynamicFormSubmission::query()
                ->whereHas('form', fn ($q) => $q->whereNull('dynamic_forms.deleted_at'));

            $totalSubmissionsCount = (clone $baseSubmissionQuery)->count();
            $latestSubmissionsPayload = (clone $baseSubmissionQuery)
                ->with('form:id,title,mode')
                ->orderByDesc('submitted_at')
                ->limit(6)
                ->get()
                ->map(fn (DynamicFormSubmission $s): array => [
                    'response_label' => 'Response #'.$s->id,
                    'form_title' => $s->form?->title ?? '-',
                    'mode_label' => $s->form?->mode?->label() ?? '-',
                    'submitted_at' => optional($s->submitted_at)->format('d M Y, H:i') ?? '-',
                    'view_url' => DynamicFormSubmissionResource::getUrl('view', ['record' => $s]),
                ])->all();
        }

        return [
            'canViewForms' => $canViewForms,
            'canViewSubmissions' => $canViewSubmissions,
            'activeFormsCount' => $activeFormsCount,
            'totalSubmissionsCount' => $totalSubmissionsCount,
            'activeForms' => $activeFormsPayload,
            'latestSubmissions' => $latestSubmissionsPayload,
            'formsIndexUrl' => $canViewForms ? DynamicFormResource::getUrl('index') : null,
            'submissionsIndexUrl' => $canViewSubmissions ? DynamicFormSubmissionResource::getUrl('index') : null,
        ];
    }
}
