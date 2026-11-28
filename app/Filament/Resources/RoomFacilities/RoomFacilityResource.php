<?php

namespace App\Filament\Resources\RoomFacilities;

use App\Enums\AdminNavigationGroup;
use App\Filament\Concerns\FormatsBilingualRecordTitle;
use App\Filament\Resources\RoomFacilities\Pages\CreateRoomFacility;
use App\Filament\Resources\RoomFacilities\Pages\EditRoomFacility;
use App\Filament\Resources\RoomFacilities\Pages\ListRoomFacilities;
use App\Filament\Resources\RoomFacilities\Schemas\RoomFacilityForm;
use App\Filament\Resources\RoomFacilities\Tables\RoomFacilitiesTable;
use App\Models\RoomFacility;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class RoomFacilityResource extends Resource
{
    use FormatsBilingualRecordTitle;
    use Translatable;

    protected static ?string $model = RoomFacility::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|\UnitEnum|null $navigationGroup = AdminNavigationGroup::ServicesRooms;

    protected static ?int $navigationSort = 2;

    /**
     * Kolom yang digunakan sebagai judul record (untuk breadcrumb & relasi).
     */
    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('Kamar Rawat Inap');
    }

    public static function getModelLabel(): string
    {
        return __('model.room_facility');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Kamar Rawat Inap');
    }

    public static function form(Schema $schema): Schema
    {
        return RoomFacilityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoomFacilitiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoomFacilities::route('/'),
            'create' => CreateRoomFacility::route('/create'),
            'edit' => EditRoomFacility::route('/{record}/edit'),
        ];
    }
}
