<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Concerns\ValidatesTranslatableLocales;
use App\Filament\Resources\Promotions\PromotionResource;
use Filament\Resources\Pages\CreateRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreatePromotion extends CreateRecord
{
    use Translatable;
    use ValidatesTranslatableLocales;

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
