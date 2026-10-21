<?php

namespace App\Filament\Resources\News\Pages;

use App\Filament\Concerns\CreatesTranslatedContent;
use App\Filament\Resources\News\NewsResource;
use Filament\Resources\Pages\CreateRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;

class CreateNews extends CreateRecord
{
    use CreatesTranslatedContent;

    protected static string $resource = NewsResource::class;

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
