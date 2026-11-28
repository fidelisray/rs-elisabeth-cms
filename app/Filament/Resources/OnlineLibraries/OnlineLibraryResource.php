<?php

namespace App\Filament\Resources\OnlineLibraries;

use App\Enums\AdminNavigationGroup;
use App\Filament\Concerns\FormatsBilingualRecordTitle;
use App\Filament\Resources\OnlineLibraries\Pages\CreateOnlineLibrary;
use App\Filament\Resources\OnlineLibraries\Pages\EditOnlineLibrary;
use App\Filament\Resources\OnlineLibraries\Pages\ListOnlineLibraries;
use App\Filament\Resources\OnlineLibraries\Schemas\OnlineLibraryForm;
use App\Filament\Resources\OnlineLibraries\Tables\OnlineLibrariesTable;
use App\Models\OnlineLibrary;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class OnlineLibraryResource extends Resource
{
    use FormatsBilingualRecordTitle;
    use Translatable;

    protected static ?string $model = OnlineLibrary::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|\UnitEnum|null $navigationGroup = AdminNavigationGroup::DoctorsLibrary;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationLabel(): string
    {
        return __('Perpustakaan Online');
    }

    public static function getModelLabel(): string
    {
        return __('Perpustakaan Online');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Perpustakaan Online');
    }

    public static function form(Schema $schema): Schema
    {
        return OnlineLibraryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OnlineLibrariesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOnlineLibraries::route('/'),
            'create' => CreateOnlineLibrary::route('/create'),
            'edit' => EditOnlineLibrary::route('/{record}/edit'),
        ];
    }
}
