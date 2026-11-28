<?php

declare(strict_types=1);

namespace App\Filament\Resources\Feedbacks\Pages;

use App\Exceptions\ExportAbortedException;
use App\Filament\Resources\Feedbacks\FeedbackResource;
use App\Models\Feedback;
use App\Models\User;
use App\Services\Export\FeedbackExportService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ListFeedbacks extends ListRecords
{
    protected static string $resource = FeedbackResource::class;

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
                ->visible(fn (): bool => Gate::allows('export', Feedback::class))
                ->authorize(fn (): bool => Gate::allows('export', Feedback::class))
                ->modalHeading(__('Ekspor Data Kritik & Saran'))
                ->modalDescription(__('Ekspor otomatis mengikuti seluruh filter aktif tabel saat ini. Header file selalu baku Bahasa Indonesia dan setiap ekspor dicatat ke Log Audit tanpa PII.'))
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

                    Checkbox::make('anonymized')
                        ->label(__('Mode Anonim (Sembunyikan Nama Pelapor, Nama Pasien, dan Nomor Telepon)'))
                        ->helperText(__('Aktifkan untuk menghasilkan lampiran siap presentasi rapat manajemen atau akreditasi tanpa identitas pribadi pasien.'))
                        ->default(false),

                    Placeholder::make('internal_note_notice')
                        ->hiddenLabel()
                        ->content(fn (): string => Auth::user()?->isSuperAdmin()
                            ? __('Sebagai Super Admin, kolom Catatan Internal akan disertakan secara otomatis.')
                            : __('Kolom Catatan Internal tidak disertakan (eksklusif Super Admin).')),
                ])
                ->action(function (array $data) {
                    /** @var User $actor */
                    $actor = Auth::user();
                    $query = $this->getFilteredTableQuery();

                    try {
                        return app(FeedbackExportService::class)->export(
                            filteredQuery: $query,
                            actor: $actor,
                            format: (string) ($data['format'] ?? 'xlsx'),
                            anonymized: (bool) ($data['anonymized'] ?? false),
                            safeFilters: $this->extractSafeFilterContext(),
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
     * Hanya catat filter terstruktur non-PII (JANGAN pernah menyalin teks bebas tableSearch ke audit_logs).
     *
     * @return array<string, mixed>
     */
    private function extractSafeFilterContext(): array
    {
        $filters = (array) ($this->tableFilters ?? []);

        return array_filter([
            'status' => filled($filters['status']['value'] ?? null) ? (string) $filters['status']['value'] : null,
            'unresolved' => (bool) ($filters['unresolved']['isActive'] ?? false) ?: null,
            'date_from' => filled($filters['submitted_at']['from'] ?? null) ? (string) $filters['submitted_at']['from'] : null,
            'date_to' => filled($filters['submitted_at']['until'] ?? null) ? (string) $filters['submitted_at']['until'] : null,
            'has_search_query' => filled($this->tableSearch),
        ], static fn ($value): bool => $value !== null);
    }
}
