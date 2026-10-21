<?php

namespace App\Filament\Resources\FacilityServices\Pages;

use App\Filament\Concerns\EditsTranslatedContent;
use App\Filament\Resources\FacilityServices\FacilityServiceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;

class EditFacilityService extends EditRecord
{
    use EditsTranslatedContent;

    protected static string $resource = FacilityServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
