<?php

namespace App\Filament\Resources\BannerPromotions\Pages;

use App\Filament\Concerns\ValidatesTranslatableLocales;
use App\Filament\Resources\BannerPromotions\BannerPromotionResource;
use Filament\Resources\Pages\CreateRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateBannerPromotion extends CreateRecord
{
    use Translatable;
    use ValidatesTranslatableLocales;

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
