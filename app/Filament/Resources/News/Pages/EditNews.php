<?php

namespace App\Filament\Resources\News\Pages;

use App\Filament\Concerns\EditsTranslatedContent;
use App\Filament\Resources\News\NewsResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;

class EditNews extends EditRecord
{
    use EditsTranslatedContent;

    protected static string $resource = NewsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('index');
    }
}
