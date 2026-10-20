<?php

namespace App\Filament\Resources\RoomFacilities\Pages;

use App\Filament\Resources\RoomFacilities\RoomFacilityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;

class ListRoomFacilities extends ListRecords
{
    use Translatable;

    protected static string $resource = RoomFacilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            CreateAction::make(),
        ];
    }
}
