<?php

namespace App\Filament\Resources\OnlineLibraries\Pages;

use App\Filament\Concerns\CreatesTranslatedContent;
use App\Filament\Resources\OnlineLibraries\OnlineLibraryResource;
use Filament\Resources\Pages\CreateRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;

class CreateOnlineLibrary extends CreateRecord
{
    use CreatesTranslatedContent;

    protected static string $resource = OnlineLibraryResource::class;

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
