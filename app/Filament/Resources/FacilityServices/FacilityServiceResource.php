<?php

namespace App\Filament\Resources\FacilityServices;

use App\Enums\AdminNavigationGroup;
use App\Filament\Concerns\FormatsBilingualRecordTitle;
use App\Filament\Resources\FacilityServices\Pages\CreateFacilityService;
use App\Filament\Resources\FacilityServices\Pages\EditFacilityService;
use App\Filament\Resources\FacilityServices\Pages\ListFacilityServices;
use App\Filament\Resources\FacilityServices\Schemas\FacilityServiceForm;
use App\Filament\Resources\FacilityServices\Tables\FacilityServiceTable;
use App\Models\FacilityService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class FacilityServiceResource extends Resource
{
    use FormatsBilingualRecordTitle;
    use Translatable;

    protected static ?string $model = FacilityService::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|\UnitEnum|null $navigationGroup = AdminNavigationGroup::ServicesRooms;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function getNavigationLabel(): string
    {
        return __('Fasilitas & Layanan');
    }

    public static function getModelLabel(): string
    {
        return __('model.facility_service');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Fasilitas & Layanan');
    }

    public static function form(Schema $schema): Schema
    {
        return FacilityServiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FacilityServiceTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFacilityServices::route('/'),
            'create' => CreateFacilityService::route('/create'),
            'edit' => EditFacilityService::route('/{record}/edit'),
        ];
    }
}
