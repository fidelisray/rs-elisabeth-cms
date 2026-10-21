<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Concerns\CreatesTranslatedContent;
use App\Filament\Resources\Promotions\PromotionResource;
use Filament\Resources\Pages\CreateRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;

class CreatePromotion extends CreateRecord
{
    use CreatesTranslatedContent;

    protected static string $resource = PromotionResource::class;

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
