<?php

declare(strict_types=1);

namespace App\Filament\Resources\Questionnaires\Pages;

use App\Exceptions\ExportAbortedException;
use App\Filament\Resources\Questionnaires\QuestionnaireResource;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Services\Export\QuestionnaireExportService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ListQuestionnaires extends ListRecords
{
    protected static string $resource = QuestionnaireResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportData')
                ->label(__('Ekspor Data'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('primary')
                ->visible(fn (): bool => Gate::allows('export', QuestionnaireResponse::class))
                ->authorize(fn (): bool => Gate::allows('export', QuestionnaireResponse::class))
                ->modalHeading(__('Ekspor Data Kuesioner Kepuasan'))
                ->modalDescription(__('Ekspor otomatis mengikuti filter layanan dan rentang tanggal aktif tabel. Jika semua layanan dipilih, berkas Excel (.xlsx) otomatis dipisahkan ke 3 worksheet (IGD, Rawat Jalan, Rawat Inap).'))
                ->modalSubmitActionLabel(__('Unduh Berkas'))
                ->schema([
                    Select::make('format')
                        ->label(__('Format Berkas'))
                        ->options(fn (): array => [
                            'xlsx' => __('Excel (.xlsx) — Direkomendasikan'),
                            'csv' => __('CSV (UTF-8)'),
                        ])
                        ->default('xlsx')
                        ->required()
                        ->native(false),

                    Placeholder::make('audit_export_notice')
                        ->hiddenLabel()
                        ->content(__('Header kolom dan skala pertanyaan tetap baku Bahasa Indonesia (siap olah Pivot Table). Setiap ekspor dicatat ke Log Audit.')),
                ])
                ->action(function (array $data) {
                    /** @var User $actor */
                    $actor = Auth::user();
                    $query = $this->getFilteredTableQuery();
                    $filters = (array) ($this->tableFilters ?? []);
                    $selectedUnit = filled($filters['questionnaire_type']['value'] ?? null)
                        ? (string) $filters['questionnaire_type']['value']
                        : null;

                    try {
                        return app(QuestionnaireExportService::class)->export(
                            filteredQuery: $query,
                            actor: $actor,
                            format: (string) ($data['format'] ?? 'xlsx'),
                            selectedUnit: $selectedUnit,
                            safeFilters: $this->extractSafeFilterContext($selectedUnit),
                        );
                    } catch (ExportAbortedException $e) {
                        Notification::make()
                            ->warning()
                            ->title($e->title)
                            ->body($e->getMessage())
                            ->send();

                        return null;
                    }
                }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractSafeFilterContext(?string $selectedUnit): array
    {
        $filters = (array) ($this->tableFilters ?? []);

        return array_filter([
            'questionnaire_type' => $selectedUnit,
            'date_from' => filled($filters['submitted_at']['from'] ?? null) ? (string) $filters['submitted_at']['from'] : null,
            'date_to' => filled($filters['submitted_at']['until'] ?? null) ? (string) $filters['submitted_at']['until'] : null,
            'has_search_query' => filled($this->tableSearch),
        ], static fn ($value): bool => $value !== null);
    }
}
