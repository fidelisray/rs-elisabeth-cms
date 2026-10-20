<?php

namespace App\Filament\Resources\FacilityServices\Pages;

use App\Filament\Concerns\ValidatesTranslatableLocales;
use App\Filament\Resources\FacilityServices\FacilityServiceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;

class EditFacilityService extends EditRecord
{
    use Translatable;
    use ValidatesTranslatableLocales;

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
