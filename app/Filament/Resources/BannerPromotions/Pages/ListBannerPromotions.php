<?php

namespace App\Filament\Resources\BannerPromotions\Pages;

use App\Filament\Resources\BannerPromotions\BannerPromotionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;

class ListBannerPromotions extends ListRecords
{
    use Translatable;

    protected static string $resource = BannerPromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
