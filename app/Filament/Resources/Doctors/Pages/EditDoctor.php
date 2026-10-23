<?php

namespace App\Filament\Resources\Doctors\Pages;

use App\Filament\Concerns\EditsTranslatedContent;
use App\Filament\Resources\Doctors\DoctorResource;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;

class EditDoctor extends EditRecord
{
    use EditsTranslatedContent;

    protected static string $resource = DoctorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('index');
    }
}
