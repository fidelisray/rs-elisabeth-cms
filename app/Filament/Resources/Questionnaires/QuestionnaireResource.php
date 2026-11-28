<?php

namespace App\Filament\Resources\Questionnaires;

use App\Enums\AdminNavigationGroup;
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

    protected static string|\UnitEnum|null $navigationGroup = AdminNavigationGroup::PatientFeedback;

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('Kuesioner Kepuasan');
    }

    public static function getModelLabel(): string
    {
        return __('model.questionnaire');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Kuesioner Kepuasan');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label(__('Nomor'))->sortable()->searchable(),
            TextColumn::make('questionnaire_type')->label(__('Layanan'))->formatStateUsing(fn ($state) => QuestionnaireResponse::TYPES[$state] ?? $state)->badge(),
            TextColumn::make('age_group')->label(__('Usia')),
            TextColumn::make('gender')->label(__('Jenis kelamin')),
            TextColumn::make('payment_method')->label(__('Cara bayar')),
            TextColumn::make('submitted_at')->label(__('Dikirim'))->dateTime('d M Y H:i')->sortable(),
        ])->filters([
            SelectFilter::make('questionnaire_type')->label(__('Layanan'))->options(QuestionnaireResponse::TYPES),
        ])->defaultSort('submitted_at', 'desc')->recordActions([ViewAction::make()])
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Pengisian'))->columns(2)->schema([
                TextEntry::make('questionnaire_type')->label(__('Layanan'))->formatStateUsing(fn ($state) => QuestionnaireResponse::TYPES[$state] ?? $state),
                TextEntry::make('submitted_at')->label(__('Dikirim'))->dateTime('d M Y H:i'),
                TextEntry::make('age_group')->label(__('Usia')),
                TextEntry::make('gender')->label(__('Jenis kelamin')),
                TextEntry::make('payment_method')->label(__('Cara bayar')),
                TextEntry::make('locale')->label(__('Bahasa')),
            ])->columnSpanFull(),
            Section::make(__('Seluruh jawaban'))->schema([
                RepeatableEntry::make('answer_rows')->hiddenLabel()
                    ->state(fn (QuestionnaireResponse $record): array => collect($record->ratings)
                        ->map(fn ($answer, $code) => ['question' => QuestionnaireDefinition::label($code), 'answer' => $answer])->values()->all())
                    ->schema([TextEntry::make('question')->label(__('Pertanyaan')), TextEntry::make('answer')->label(__('Jawaban'))])->columns(2),
                TextEntry::make('suggestions')->label(__('Saran lengkap'))->placeholder(__('Tidak diisi.')),
            ])->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListQuestionnaires::route('/'), 'view' => ViewQuestionnaire::route('/{record}')];
    }
}
