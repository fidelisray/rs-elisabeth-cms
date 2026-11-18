<?php

namespace App\Filament\Resources\DynamicFormSubmissions\Pages;

use App\Filament\Resources\DynamicFormSubmissions\DynamicFormSubmissionResource;
use Filament\Resources\Pages\ListRecords;

class ListDynamicFormSubmissions extends ListRecords
{
    protected static string $resource = DynamicFormSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
