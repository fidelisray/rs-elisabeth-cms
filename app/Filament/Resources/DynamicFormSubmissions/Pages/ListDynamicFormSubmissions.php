<?php

declare(strict_types=1);

namespace App\Filament\Resources\DynamicFormSubmissions\Pages;

use App\Exceptions\ExportAbortedException;
use App\Filament\Resources\DynamicFormSubmissions\DynamicFormSubmissionResource;
use App\Models\DynamicForm;
use App\Models\DynamicFormSubmission;
use App\Models\DynamicFormVersion;
use App\Models\User;
use App\Services\DynamicForm\DynamicFormExportService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ListDynamicFormSubmissions extends ListRecords
{
    protected static string $resource = DynamicFormSubmissionResource::class;

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
                ->visible(fn (): bool => Gate::allows('export', DynamicFormSubmission::class))
                ->authorize(fn (): bool => Gate::allows('export', DynamicFormSubmission::class))
                ->modalHeading(__('Ekspor Respons Formulir Kustom'))
                ->modalDescription(__('Pilih 1 formulir target agar struktur kolom pertanyaan rapi sesuai skema formulir. Filter versi dan rentang tanggal aktif tabel otomatis diterapkan, dan setiap ekspor dicatat ke Log Audit.'))
                ->modalSubmitActionLabel(__('Unduh Berkas'))
                ->fillForm(fn (): array => [
                    'dynamic_form_id' => $this->activeFormIdFromTableFilter(),
                    'format' => 'xlsx',
                ])
                ->schema([
                    Select::make('dynamic_form_id')
                        ->label(__('Formulir Target'))
                        ->options(fn (): array => DynamicForm::query()
                            ->orderBy('title')
                            ->pluck('title', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->required(),

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
                        ->content(__('Header kolom tetap baku Bahasa Indonesia (siap olah Pivot Table). Setiap ekspor dicatat ke Log Audit tanpa menyalin jawaban responden.')),
                ])
                ->action(function (array $data) {
                    $form = DynamicForm::query()->find($data['dynamic_form_id'] ?? null);
                    if (! $form) {
                        Notification::make()
                            ->warning()
                            ->title(__('Formulir target tidak ditemukan'))
                            ->send();

                        return null;
                    }

                    Gate::authorize('export', DynamicFormSubmission::class);
                    /** @var User $actor */
                    $actor = Auth::user();
                    $filters = $this->extractExportFiltersForForm($form);
                    $format = (string) ($data['format'] ?? 'xlsx');

                    try {
                        $exportService = app(DynamicFormExportService::class);

                        return $format === 'csv'
                            ? $exportService->streamCsv($form, $filters, $actor)
                            : $exportService->streamXlsx($form, $filters, $actor);
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

    private function activeFormIdFromTableFilter(): ?int
    {
        $raw = ((array) ($this->tableFilters ?? []))['dynamic_form_id']['value'] ?? null;

        return is_numeric($raw) ? (int) $raw : null;
    }

    /**
     * @return array{version_id: ?int, date_from: ?string, date_to: ?string}
     */
    private function extractExportFiltersForForm(DynamicForm $form): array
    {
        $filters = (array) ($this->tableFilters ?? []);
        $rawVersionId = $filters['dynamic_form_version_id']['value'] ?? null;
        $versionId = null;

        // Hanya teruskan filter versi jika versi tersebut milik formulir yang dipilih.
        if (is_numeric($rawVersionId)) {
            $belongsToForm = DynamicFormVersion::query()
                ->where('id', (int) $rawVersionId)
                ->where('dynamic_form_id', $form->id)
                ->exists();
            if ($belongsToForm) {
                $versionId = (int) $rawVersionId;
            }
        }

        return [
            'version_id' => $versionId,
            'date_from' => filled($filters['submitted_at']['from'] ?? null) ? (string) $filters['submitted_at']['from'] : null,
            'date_to' => filled($filters['submitted_at']['until'] ?? null) ? (string) $filters['submitted_at']['until'] : null,
        ];
    }
}
