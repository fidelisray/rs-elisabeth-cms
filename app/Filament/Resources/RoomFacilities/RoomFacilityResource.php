<?php

namespace App\Filament\Resources\RoomFacilities;

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

class RoomFacilityResource extends Resource
{
    protected static ?string $model = RoomFacility::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    /**
     * Label yang muncul di sidebar navigasi admin panel.
     */
    protected static ?string $navigationLabel = 'Room Facilities';

    /**
     * Label plural untuk list page heading.
     */
    protected static ?string $pluralModelLabel = 'Room Facilities';

    /**
     * Label singular untuk create/edit page heading.
     */
    protected static ?string $modelLabel = 'Room Facility';

    /**
     * Kolom yang digunakan sebagai judul record (untuk breadcrumb & relasi).
     */
    protected static ?string $recordTitleAttribute = 'name';

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
