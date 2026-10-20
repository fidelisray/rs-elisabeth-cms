<?php

namespace App\Filament\Resources\RoomFacilities\Pages;

use App\Filament\Resources\RoomFacilities\RoomFacilityResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;

class EditRoomFacility extends EditRecord
{
    use Translatable;

    protected static string $resource = RoomFacilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('index');
    }
}
