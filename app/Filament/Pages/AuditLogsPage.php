<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\AdminNavigationGroup;
use App\Models\AuditLog;
use App\Services\IntegrationTelemetryService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use UnitEnum;

class AuditLogsPage extends Page implements HasTable
{
    use InteractsWithTable;

    private const NO_SUBJECT_FILTER_VALUE = '__none__';

    protected string $view = 'filament.pages.audit-logs-page';

    protected static ?string $slug = 'audit-logs';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::AccessControl;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('Log Audit');
    }

    public function getTitle(): string
    {
        return __('Log Audit');
    }

    public function getHeading(): string
    {
        return __('Log Audit');
    }

    public function getSubheading(): ?string
    {
        return __('Riwayat append-only kejadian keamanan dan aksi operasional kritis (eksklusif Super Admin, tanpa PII).');
    }

    public static function canAccess(): bool
    {
        return (bool) Auth::user()?->isSuperAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(AuditLog::query()->latest('created_at')->latest('id'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Waktu'))
                    ->dateTime('d M Y, H:i:s')
                    ->sortable(),

                TextColumn::make('actor_email')
                    ->label(__('Aktor'))
                    ->getStateUsing(fn (AuditLog $record): string => filled($record->actor_email)
                        ? (string) $record->actor_email
                        : __('Sistem / Konsol'))
                    ->description(fn (AuditLog $record): string => self::actorTypeLabel($record->actor_type))
                    ->searchable(),

                TextColumn::make('action')
                    ->label(__('Aksi'))
                    ->badge()
                    ->color(fn (?string $state): string => self::actionColor($state))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subject_type')
                    ->label(__('Subjek'))
                    ->getStateUsing(fn (AuditLog $record): string => self::formatSubject($record))
                    ->description(fn (AuditLog $record): ?string => filled($record->subject_email) ? (string) $record->subject_email : null)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search): void {
                            $q->where('subject_email', 'like', "%{$search}%")
                                ->orWhere('subject_type', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('metadata_summary')
                    ->label(__('Ringkasan Metadata'))
                    ->getStateUsing(fn (AuditLog $record): string => self::summarizeMetadata($record))
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label(__('Aksi'))
                    ->options(fn (): array => AuditLog::query()
                        ->whereNotNull('action')
                        ->distinct()
                        ->orderBy('action')
                        ->pluck('action', 'action')
                        ->all()),

                SelectFilter::make('actor_type')
                    ->label(__('Tipe Aktor'))
                    ->options(fn (): array => [
                        'user' => __('Pengguna Panel'),
                        'console' => __('Konsol CLI'),
                        'system' => __('Sistem'),
                    ]),

                SelectFilter::make('subject_type')
                    ->label(__('Jenis Subjek'))
                    ->options(function (): array {
                        $types = AuditLog::query()
                            ->whereNotNull('subject_type')
                            ->distinct()
                            ->orderBy('subject_type')
                            ->pluck('subject_type')
                            ->all();

                        $options = [
                            self::NO_SUBJECT_FILTER_VALUE => __('Sistem / Tanpa Subjek'),
                        ];

                        foreach ($types as $type) {
                            $options[(string) $type] = class_basename((string) $type);
                        }

                        return $options;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if (blank($value)) {
                            return $query;
                        }

                        if ($value === self::NO_SUBJECT_FILTER_VALUE) {
                            return $query->whereNull('subject_type');
                        }

                        return $query->where('subject_type', $value);
                    }),

                Filter::make('created_at')
                    ->label(__('Rentang Tanggal'))
                    ->schema([
                        DatePicker::make('from')
                            ->label(__('Dari Tanggal')),
                        DatePicker::make('until')
                            ->label(__('Sampai Tanggal')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['from'] ?? null),
                                fn (Builder $q): Builder => $q->whereDate('created_at', '>=', (string) $data['from']),
                            )
                            ->when(
                                filled($data['until'] ?? null),
                                fn (Builder $q): Builder => $q->whereDate('created_at', '<=', (string) $data['until']),
                            );
                    }),
            ])
            ->recordActions([
                Action::make('inspect_metadata')
                    ->label(__('Inspeksi Metadata'))
                    ->icon(Heroicon::OutlinedMagnifyingGlassCircle)
                    ->color('info')
                    ->modalHeading(__('Detail Metadata Log Audit'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Tutup'))
                    ->modalContent(function (AuditLog $record): View {
                        $sanitized = IntegrationTelemetryService::sanitizeMetadata((array) ($record->metadata ?? []));

                        return view('filament.pages.partials.audit-log-metadata-modal', [
                            'record' => $record,
                            'actorTypeLabel' => self::actorTypeLabel($record->actor_type),
                            'subjectLabel' => self::formatSubject($record),
                            'prettyMetadata' => json_encode($sanitized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                        ]);
                    }),
            ])
            ->emptyStateHeading(__('Belum ada catatan log audit'))
            ->emptyStateDescription(__('Kejadian keamanan penting, tindak lanjut masukan pasien, dan aksi operasional sistem akan tampil secara otomatis di sini.'))
            ->paginationPageOptions([15, 25, 50]);
    }

    public static function actorTypeLabel(?string $type): string
    {
        return match ($type) {
            'user' => __('Pengguna Panel'),
            'console' => __('Konsol CLI'),
            'system' => __('Sistem'),
            default => (string) ($type ?: '-'),
        };
    }

    public static function actionColor(?string $action): string
    {
        if (! is_string($action) || $action === '') {
            return 'gray';
        }

        if (str_contains($action, 'force_delete') || str_contains($action, 'password_reset') || str_contains($action, 'deactivated')) {
            return 'danger';
        }

        if (str_starts_with($action, 'super_admin.') || str_starts_with($action, 'role.')) {
            return 'warning';
        }

        if (str_starts_with($action, 'cache.')) {
            return 'info';
        }

        return 'success';
    }

    public static function formatSubject(AuditLog $record): string
    {
        if (blank($record->subject_type)) {
            return __('Sistem / Tanpa Subjek');
        }

        $base = class_basename((string) $record->subject_type);

        return filled($record->subject_id)
            ? "{$base} #{$record->subject_id}"
            : $base;
    }

    public static function summarizeMetadata(AuditLog $record): string
    {
        $sanitized = IntegrationTelemetryService::sanitizeMetadata((array) ($record->metadata ?? []));

        if ($sanitized === []) {
            return '—';
        }

        $parts = [];
        foreach ($sanitized as $key => $value) {
            if (is_bool($value)) {
                $formatted = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $formatted = '['.count($value).']';
            } elseif ($value === null || $value === '') {
                $formatted = '-';
            } else {
                $formatted = (string) $value;
            }

            $parts[] = "{$key}: {$formatted}";
        }

        return Str::limit(implode(' · ', $parts), 110, '...');
    }
}
