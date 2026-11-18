<?php

namespace App\Filament\Resources\DynamicForms\Pages;

use App\Filament\Resources\DynamicForms\DynamicFormResource;
use App\Models\DynamicForm;
use App\Models\DynamicFormSubmission;
use App\Services\DynamicForm\DynamicFormAnalyticsService;
use App\Services\DynamicForm\DynamicFormExportService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ManageDynamicFormAnalytics extends Page
{
    use InteractsWithRecord;

    protected static string $resource = DynamicFormResource::class;

    protected string $view = 'filament.resources.dynamic-forms.pages.analytics';

    public string $versionFilter = 'all';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?string $errorMessage = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->mountCanAuthorizeAccess();
    }

    public function getTitle(): string
    {
        return 'Ringkasan & Analitik: '.$this->getDynamicForm()->title;
    }

    public function getDynamicForm(): DynamicForm
    {
        /** @var DynamicForm $form */
        $form = $this->getRecord();

        return $form;
    }

    public function resetFilters(): void
    {
        $this->versionFilter = 'all';
        $this->dateFrom = null;
        $this->dateTo = null;
        $this->errorMessage = null;
    }

    public function exportCsv(): ?StreamedResponse
    {
        $this->authorizeExport();

        try {
            return app(DynamicFormExportService::class)->streamCsv(
                $this->getDynamicForm(),
                $this->activeFilters()
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->errorMessage = 'Ekspor CSV gagal karena filter tanggal tidak valid atau data gagal dibaca.';
            Notification::make()->danger()->title('Ekspor CSV gagal')->body($this->errorMessage)->send();

            return null;
        }
    }

    public function exportXlsx(): ?BinaryFileResponse
    {
        $this->authorizeExport();

        try {
            return app(DynamicFormExportService::class)->streamXlsx(
                $this->getDynamicForm(),
                $this->activeFilters()
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->errorMessage = 'Ekspor Excel gagal karena filter tanggal tidak valid atau data gagal dibaca.';
            Notification::make()->danger()->title('Ekspor Excel gagal')->body($this->errorMessage)->send();

            return null;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Ekspor CSV')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->authorize(fn (): bool => Gate::allows('view', $this->getDynamicForm()))
                ->action(fn () => $this->exportCsv()),

            Action::make('exportXlsx')
                ->label('Ekspor Excel (.xlsx)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('primary')
                ->authorize(fn (): bool => Gate::allows('view', $this->getDynamicForm()))
                ->action(fn () => $this->exportXlsx()),

            Action::make('editForm')
                ->label('Sunting Form')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->url(fn (): string => DynamicFormResource::getUrl('edit', ['record' => $this->getDynamicForm()]))
                ->visible(fn (): bool => Gate::allows('update', $this->getDynamicForm())),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $form = $this->getDynamicForm();
        $analytics = ['summary' => [], 'questions' => []];
        $this->errorMessage = null;

        try {
            $analytics = app(DynamicFormAnalyticsService::class)->summarize($form, $this->activeFilters());
        } catch (Throwable $exception) {
            report($exception);
            $this->errorMessage = 'Data analitik gagal dimuat. Periksa rentang tanggal atau coba muat ulang halaman.';
        }

        $versions = $form->versions()->orderByDesc('version_number')->get();

        return [
            'formRecord' => $form,
            'analytics' => $analytics,
            'versions' => $versions,
            'hasActiveFilters' => $this->versionFilter !== 'all' || filled($this->dateFrom) || filled($this->dateTo),
            'canViewSubmissions' => Gate::allows('viewAny', DynamicFormSubmission::class),
        ];
    }

    /**
     * @return array{version_id: int|string, date_from: ?string, date_to: ?string}
     */
    private function activeFilters(): array
    {
        return [
            'version_id' => $this->versionFilter,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ];
    }

    private function authorizeExport(): void
    {
        Gate::authorize('view', $this->getDynamicForm());
    }
}
