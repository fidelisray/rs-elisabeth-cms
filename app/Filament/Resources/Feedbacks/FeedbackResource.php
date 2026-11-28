<?php

namespace App\Filament\Resources\Feedbacks;

use App\Enums\AdminNavigationGroup;
use App\Filament\Resources\Feedbacks\Pages\ListFeedbacks;
use App\Filament\Resources\Feedbacks\Pages\ViewFeedback;
use App\Models\Feedback;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FeedbackResource extends Resource
{
    protected static ?string $model = Feedback::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|\UnitEnum|null $navigationGroup = AdminNavigationGroup::PatientFeedback;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'ticket_number';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function getNavigationLabel(): string
    {
        return __('Kritik & Saran');
    }

    public static function getModelLabel(): string
    {
        return __('model.feedback');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Kritik & Saran');
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'baru' => __('Baru'),
            'diproses' => __('Diproses'),
            'selesai' => __('Selesai'),
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return self::statusOptions()[$status ?? ''] ?? (string) $status;
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            'baru' => 'warning',
            'diproses' => 'info',
            'selesai' => 'success',
            default => 'gray',
        };
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('ticket_number')->label(__('Tiket'))->searchable()->sortable(),
            TextColumn::make('reporter_name')->label(__('Pelapor'))->searchable(),
            TextColumn::make('patient_name')->label(__('Pasien'))->searchable(),
            TextColumn::make('status')
                ->label(__('Status'))
                ->badge()
                ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
                ->color(fn (?string $state): string => self::statusColor($state)),
            TextColumn::make('submitted_at')->label(__('Dikirim'))->dateTime('d M Y H:i')->sortable(),
        ])->filters([
            SelectFilter::make('status')
                ->label(__('Status'))
                ->options(fn (): array => self::statusOptions()),
            Filter::make('unresolved')
                ->label(__('Belum Selesai (Baru & Diproses)'))
                ->query(fn (Builder $query): Builder => $query->whereIn('status', ['baru', 'diproses'])),
            Filter::make('submitted_at')
                ->label(__('Rentang Tanggal Kirim'))
                ->schema([
                    DatePicker::make('from')->label(__('Dari Tanggal')),
                    DatePicker::make('until')->label(__('Sampai Tanggal')),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            filled($data['from'] ?? null),
                            fn (Builder $q): Builder => $q->whereDate('submitted_at', '>=', (string) $data['from']),
                        )
                        ->when(
                            filled($data['until'] ?? null),
                            fn (Builder $q): Builder => $q->whereDate('submitted_at', '<=', (string) $data['until']),
                        );
                }),
        ])->defaultSort('submitted_at', 'desc')
            ->recordActions([ViewAction::make()])
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Identitas pengiriman'))->columns(2)->schema([
                TextEntry::make('ticket_number')->label(__('Tiket')),
                TextEntry::make('submitted_at')->label(__('Dikirim'))->dateTime('d M Y H:i'),
                TextEntry::make('reporter_name')->label(__('Pelapor')),
                TextEntry::make('relation_to_patient')->label(__('Hubungan dengan pasien')),
                TextEntry::make('relation_other')->label(__('Hubungan lainnya'))->placeholder('—'),
                TextEntry::make('patient_name')->label(__('Pasien')),
                TextEntry::make('phone_number')->label(__('Telepon')),
                TextEntry::make('locale')->label(__('Bahasa')),
            ])->columnSpanFull(),
            Section::make(__('Isi laporan'))->schema([
                TextEntry::make('categories')->label(__('Kategori'))->listWithLineBreaks(),
                TextEntry::make('category_other')->label(__('Kategori lainnya'))->placeholder('—'),
                TextEntry::make('complaint_detail')->label(__('Keluhan lengkap')),
                TextEntry::make('expected_solution')->label(__('Solusi yang diharapkan')),
            ])->columnSpanFull(),
            Section::make(__('Tindak lanjut'))->schema([
                TextEntry::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::statusLabel($state))
                    ->color(fn (?string $state): string => self::statusColor($state)),
                TextEntry::make('internal_note')->label(__('Catatan internal'))->placeholder(__('Belum ada catatan.')),
            ])->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListFeedbacks::route('/'), 'view' => ViewFeedback::route('/{record}')];
    }
}
