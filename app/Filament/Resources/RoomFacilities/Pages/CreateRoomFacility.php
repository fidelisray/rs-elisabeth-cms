<?php

namespace App\Filament\Resources\RoomFacilities\Pages;

use App\Filament\Concerns\CreatesTranslatedContent;
use App\Filament\Resources\RoomFacilities\RoomFacilityResource;
use Filament\Resources\Pages\CreateRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;

class CreateRoomFacility extends CreateRecord
{
    use CreatesTranslatedContent;

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
