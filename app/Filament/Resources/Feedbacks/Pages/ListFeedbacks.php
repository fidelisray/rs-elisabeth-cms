<?php

namespace App\Filament\Resources\Feedbacks\Pages;

use App\Filament\Resources\Feedbacks\FeedbackResource;
use Filament\Resources\Pages\ListRecords;

class ListFeedbacks extends ListRecords
{
    protected static string $resource = FeedbackResource::class;
}
