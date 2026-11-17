<?php

namespace App\Filament\Resources\RoomFacilities\Pages;

use App\Filament\Concerns\CreatesTranslatedContent;
use App\Filament\Resources\RoomFacilities\RoomFacilityResource;
use App\Support\RoomNestedContent;
use Filament\Resources\Pages\CreateRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;

class CreateRoomFacility extends CreateRecord
{
    use CreatesTranslatedContent;

    protected static string $resource = RoomFacilityResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['main_amenities']) || isset($data['additional_amenities'])) {
            $data['amenities'] = RoomNestedContent::combineAmenities(
                $data['main_amenities'] ?? [],
                $data['additional_amenities'] ?? []
            );
            unset($data['main_amenities'], $data['additional_amenities']);
        }

        $data['room_size'] = null;

        return $data;
    }

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
