<?php

namespace App\Filament\Resources\FacilityServices\Pages;

use App\Filament\Resources\FacilityServices\FacilityServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFacilityServices extends ListRecords
{
    protected static string $resource = FacilityServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
