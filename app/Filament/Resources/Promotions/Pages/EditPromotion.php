<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Concerns\EditsTranslatedContent;
use App\Filament\Resources\Promotions\PromotionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;

class EditPromotion extends EditRecord
{
    use EditsTranslatedContent;

    protected static string $resource = PromotionResource::class;

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
