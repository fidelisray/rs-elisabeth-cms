<?php

namespace App\Filament\Resources\RoomFacilities\Pages;

use App\Filament\Resources\RoomFacilities\RoomFacilityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRoomFacilities extends ListRecords
{
    protected static string $resource = RoomFacilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'premium' => Tab::make('Premium')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('category', 'premium')),
            'standard' => Tab::make('Standard')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('category', 'standard')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'premium';
    }
}
