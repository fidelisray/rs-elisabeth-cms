<?php

namespace App\Filament\Resources\OnlineLibraries\Pages;

use App\Filament\Resources\OnlineLibraries\OnlineLibraryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;

class ListOnlineLibraries extends ListRecords
{
    use Translatable;

    protected static string $resource = OnlineLibraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
