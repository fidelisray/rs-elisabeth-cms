<?php

namespace App\Filament\Resources\Questionnaires\Pages;

use App\Filament\Resources\Questionnaires\QuestionnaireResource;
use Filament\Resources\Pages\ListRecords;

class ListQuestionnaires extends ListRecords
{
    protected static string $resource = QuestionnaireResource::class;
}
