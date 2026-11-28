<?php

namespace App\Filament\Resources\DynamicForms;

use App\Enums\AdminNavigationGroup;
use App\Enums\DynamicFormMode;
use App\Enums\DynamicFormStatus;
use App\Filament\Resources\DynamicForms\Pages\CreateDynamicForm;
use App\Filament\Resources\DynamicForms\Pages\EditDynamicForm;
use App\Filament\Resources\DynamicForms\Pages\ListDynamicForms;
use App\Filament\Resources\DynamicForms\Pages\ManageDynamicFormAnalytics;
use App\Filament\Resources\DynamicForms\Pages\ViewDynamicForm;
use App\Filament\Resources\DynamicForms\Tables\DynamicFormsTable;
use App\Models\DynamicForm;
use App\Services\DynamicForm\DynamicFormHtmlSanitizer;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DynamicFormResource extends Resource
{
    protected static ?string $model = DynamicForm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = AdminNavigationGroup::PatientFeedback;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationLabel(): string
    {
        return __('nav.dynamic_form');
    }

    public static function getModelLabel(): string
    {
        return __('model.dynamic_form');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Formulir Kuesioner Kustom');
    }

    public static function table(Table $table): Table
    {
        return DynamicFormsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Informasi Formulir'))
                ->columns(2)
                ->schema([
                    TextEntry::make('title')->label(__('Judul Formulir')),
                    TextEntry::make('slug')
                        ->label(__('Path Web'))
                        ->formatStateUsing(fn (string $state): string => '/formulir/'.$state),
                    TextEntry::make('mode')
                        ->label(__('Mode'))
                        ->formatStateUsing(fn (DynamicFormMode|string $state): string => $state instanceof DynamicFormMode
                            ? $state->label()
                            : (string) $state),
                    TextEntry::make('locale')
                        ->label(__('Bahasa'))
                        ->formatStateUsing(fn (string $state): string => strtoupper($state)),
                    TextEntry::make('status')
                        ->label(__('Status'))
                        ->badge()
                        ->formatStateUsing(fn (DynamicFormStatus|string $state): string => $state instanceof DynamicFormStatus
                            ? $state->label()
                            : (string) $state)
                        ->color(fn (DynamicFormStatus|string $state): string => match ($state instanceof DynamicFormStatus ? $state : DynamicFormStatus::tryFrom((string) $state)) {
                            DynamicFormStatus::Published => 'success',
                            DynamicFormStatus::Closed => 'danger',
                            default => 'gray',
                        }),
                    TextEntry::make('is_public_listed')
                        ->label(__('Katalog Web'))
                        ->badge()
                        ->formatStateUsing(fn (mixed $state): string => (bool) $state ? __('Katalog Publik') : __('Link / QR Saja'))
                        ->color(fn (mixed $state): string => (bool) $state ? 'success' : 'gray'),
                    TextEntry::make('publishedVersion.version_number')
                        ->label(__('Versi Terpublikasi'))
                        ->formatStateUsing(fn ($state): string => $state ? 'v'.$state : __('Belum dipublikasikan')),
                    TextEntry::make('starts_at')->label(__('Waktu Mulai'))->dateTime('d M Y H:i')->placeholder(__('Langsung aktif')),
                    TextEntry::make('ends_at')->label(__('Waktu Berakhir'))->dateTime('d M Y H:i')->placeholder(__('Tidak dibatasi')),
                    TextEntry::make('max_responses')->label(__('Kuota Respons'))->placeholder(__('Tidak dibatasi')),
                    TextEntry::make('description')
                        ->label(__('Deskripsi'))
                        ->placeholder(__('Tidak ada deskripsi.'))
                        ->formatStateUsing(fn (mixed $state): ?string => app(DynamicFormHtmlSanitizer::class)->sanitize($state))
                        ->html()
                        ->prose()
                        ->columnSpanFull(),
                ])->columnSpanFull(),
        ]);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDynamicForms::route('/'),
            'create' => CreateDynamicForm::route('/create'),
            'view' => ViewDynamicForm::route('/{record}'),
            'edit' => EditDynamicForm::route('/{record}/edit'),
            'analytics' => ManageDynamicFormAnalytics::route('/{record}/analytics'),
        ];
    }
}
