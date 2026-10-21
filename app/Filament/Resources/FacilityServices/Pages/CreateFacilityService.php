<?php

namespace App\Filament\Resources\FacilityServices\Pages;

use App\Filament\Concerns\CreatesTranslatedContent;
use App\Filament\Resources\FacilityServices\FacilityServiceResource;
use Filament\Resources\Pages\CreateRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;

class CreateFacilityService extends CreateRecord
{
    use CreatesTranslatedContent;

    protected static string $resource = FacilityServiceResource::class;

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
