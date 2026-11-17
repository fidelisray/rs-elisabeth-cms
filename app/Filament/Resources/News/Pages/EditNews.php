<?php

namespace App\Filament\Resources\News\Pages;

use App\Filament\Concerns\EditsTranslatedContent;
use App\Filament\Resources\News\NewsResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNews extends EditRecord
{
    use EditsTranslatedContent;

    protected static string $resource = NewsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('index');
    }
}
