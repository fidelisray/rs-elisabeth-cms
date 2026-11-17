<?php

namespace App\Filament\Resources\RoomFacilities\Pages;

use App\Filament\Concerns\EditsTranslatedContent;
use App\Filament\Resources\RoomFacilities\RoomFacilityResource;
use App\Support\RoomNestedContent;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRoomFacility extends EditRecord
{
    use EditsTranslatedContent;

    protected static string $resource = RoomFacilityResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (isset($data['amenities'])) {
            $split = RoomNestedContent::splitAmenities($data['amenities']);
            $data['main_amenities'] = $split['main'];
            $data['additional_amenities'] = $split['additional'];
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
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
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('index');
    }
}
