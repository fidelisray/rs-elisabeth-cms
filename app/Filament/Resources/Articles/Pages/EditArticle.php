<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Concerns\EditsTranslatedContent;
use App\Filament\Resources\Articles\ArticleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;

class EditArticle extends EditRecord
{
    use EditsTranslatedContent;

    protected static string $resource = ArticleResource::class;

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
