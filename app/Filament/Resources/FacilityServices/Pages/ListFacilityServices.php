<?php

namespace App\Filament\Resources\FacilityServices\Pages;

use App\Filament\Resources\FacilityServices\FacilityServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;

class ListFacilityServices extends ListRecords
{
    use Translatable;

    protected static string $resource = FacilityServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
