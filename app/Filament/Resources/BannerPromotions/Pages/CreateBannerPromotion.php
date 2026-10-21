<?php

namespace App\Filament\Resources\BannerPromotions\Pages;

use App\Filament\Concerns\CreatesTranslatedContent;
use App\Filament\Resources\BannerPromotions\BannerPromotionResource;
use Filament\Resources\Pages\CreateRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;

class CreateBannerPromotion extends CreateRecord
{
    use CreatesTranslatedContent;

    protected static string $resource = BannerPromotionResource::class;

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
