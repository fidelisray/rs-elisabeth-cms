<?php

namespace App\Filament\Resources\Questionnaires;

use App\Filament\Resources\Questionnaires\Pages\ListQuestionnaires;
use App\Filament\Resources\Questionnaires\Pages\ViewQuestionnaire;
use App\Models\QuestionnaireResponse;
use App\Support\QuestionnaireDefinition;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class QuestionnaireResource extends Resource
{
    protected static ?string $model = QuestionnaireResponse::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'Kuesioner';

    protected static ?string $pluralModelLabel = 'Kuesioner Kepuasan';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('Nomor')->sortable()->searchable(),
            TextColumn::make('questionnaire_type')->label('Layanan')->formatStateUsing(fn ($state) => QuestionnaireResponse::TYPES[$state] ?? $state)->badge(),
            TextColumn::make('age_group')->label('Usia'),
            TextColumn::make('gender')->label('Jenis kelamin'),
            TextColumn::make('payment_method')->label('Cara bayar'),
            TextColumn::make('submitted_at')->label('Dikirim')->dateTime('d M Y H:i')->sortable(),
        ])->filters([
            SelectFilter::make('questionnaire_type')->label('Layanan')->options(QuestionnaireResponse::TYPES),
        ])->defaultSort('submitted_at', 'desc')->recordActions([ViewAction::make()])
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pengisian')->columns(2)->schema([
                TextEntry::make('questionnaire_type')->label('Layanan')->formatStateUsing(fn ($state) => QuestionnaireResponse::TYPES[$state] ?? $state),
                TextEntry::make('submitted_at')->label('Dikirim')->dateTime('d M Y H:i'),
                TextEntry::make('age_group')->label('Usia'),
                TextEntry::make('gender')->label('Jenis kelamin'),
                TextEntry::make('payment_method')->label('Cara bayar'),
                TextEntry::make('locale')->label('Bahasa'),
            ])->columnSpanFull(),
            Section::make('Seluruh jawaban')->schema([
                RepeatableEntry::make('answer_rows')->hiddenLabel()
                    ->state(fn (QuestionnaireResponse $record): array => collect($record->ratings)
                        ->map(fn ($answer, $code) => ['question' => QuestionnaireDefinition::label($code), 'answer' => $answer])->values()->all())
                    ->schema([TextEntry::make('question')->label('Pertanyaan'), TextEntry::make('answer')->label('Jawaban')])->columns(2),
                TextEntry::make('suggestions')->label('Saran lengkap')->placeholder('Tidak diisi.'),
            ])->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListQuestionnaires::route('/'), 'view' => ViewQuestionnaire::route('/{record}')];
    }
}
