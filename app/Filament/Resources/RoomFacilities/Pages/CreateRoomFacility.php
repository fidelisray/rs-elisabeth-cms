<?php

namespace App\Filament\Resources\RoomFacilities\Pages;

use App\Filament\Concerns\ValidatesTranslatableLocales;
use App\Filament\Resources\RoomFacilities\RoomFacilityResource;
use Filament\Resources\Pages\CreateRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateRoomFacility extends CreateRecord
{
    use Translatable;
    use ValidatesTranslatableLocales;

    protected static string $resource = RoomFacilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
