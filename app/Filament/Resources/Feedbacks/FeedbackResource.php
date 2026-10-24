<?php

namespace App\Filament\Resources\Feedbacks;

use App\Filament\Resources\Feedbacks\Pages\ListFeedbacks;
use App\Filament\Resources\Feedbacks\Pages\ViewFeedback;
use App\Models\Feedback;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FeedbackResource extends Resource
{
    protected static ?string $model = Feedback::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $modelLabel = 'Kritik & Saran';

    protected static ?string $pluralModelLabel = 'Kritik & Saran';

    protected static ?string $recordTitleAttribute = 'ticket_number';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('ticket_number')->label('Tiket')->searchable()->sortable(),
            TextColumn::make('reporter_name')->label('Pelapor')->searchable(),
            TextColumn::make('patient_name')->label('Pasien')->searchable(),
            TextColumn::make('status')->label('Status')->badge(),
            TextColumn::make('submitted_at')->label('Dikirim')->dateTime('d M Y H:i')->sortable(),
        ])->filters([
            SelectFilter::make('status')->options(array_combine(Feedback::STATUSES, Feedback::STATUSES)),
        ])->defaultSort('submitted_at', 'desc')
            ->recordActions([ViewAction::make()])
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas pengiriman')->columns(2)->schema([
                TextEntry::make('ticket_number')->label('Tiket'),
                TextEntry::make('submitted_at')->label('Dikirim')->dateTime('d M Y H:i'),
                TextEntry::make('reporter_name')->label('Pelapor'),
                TextEntry::make('relation_to_patient')->label('Hubungan dengan pasien'),
                TextEntry::make('relation_other')->label('Hubungan lainnya')->placeholder('—'),
                TextEntry::make('patient_name')->label('Pasien'),
                TextEntry::make('phone_number')->label('Telepon'),
                TextEntry::make('locale')->label('Bahasa'),
            ])->columnSpanFull(),
            Section::make('Isi laporan')->schema([
                TextEntry::make('categories')->label('Kategori')->listWithLineBreaks(),
                TextEntry::make('category_other')->label('Kategori lainnya')->placeholder('—'),
                TextEntry::make('complaint_detail')->label('Keluhan lengkap'),
                TextEntry::make('expected_solution')->label('Solusi yang diharapkan'),
            ])->columnSpanFull(),
            Section::make('Tindak lanjut')->schema([
                TextEntry::make('status')->badge(),
                TextEntry::make('internal_note')->label('Catatan internal')->placeholder('Belum ada catatan.'),
            ])->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListFeedbacks::route('/'), 'view' => ViewFeedback::route('/{record}')];
    }
}
