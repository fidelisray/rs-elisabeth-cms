<?php

namespace App\Filament\Resources\FacilityServices\Pages;

use App\Filament\Concerns\CreatesTranslatedContent;
use App\Filament\Resources\FacilityServices\FacilityServiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFacilityService extends CreateRecord
{
    use CreatesTranslatedContent;

    protected static string $resource = FacilityServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
