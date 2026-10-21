<?php

namespace App\Filament\Resources\BannerPromotions\Pages;

use App\Filament\Concerns\EditsTranslatedContent;
use App\Filament\Resources\BannerPromotions\BannerPromotionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;

class EditBannerPromotion extends EditRecord
{
    use EditsTranslatedContent;

    protected static string $resource = BannerPromotionResource::class;

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
