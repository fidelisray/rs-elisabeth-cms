<?php

namespace App\Filament\Resources\OnlineLibraries\Pages;

use App\Filament\Concerns\EditsTranslatedContent;
use App\Filament\Resources\OnlineLibraries\OnlineLibraryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOnlineLibrary extends EditRecord
{
    use EditsTranslatedContent;

    protected static string $resource = OnlineLibraryResource::class;

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
