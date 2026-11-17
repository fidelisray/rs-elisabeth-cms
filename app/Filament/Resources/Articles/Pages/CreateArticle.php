<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Concerns\CreatesTranslatedContent;
use App\Filament\Resources\Articles\ArticleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateArticle extends CreateRecord
{
    use CreatesTranslatedContent;

    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
