<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;
use LaraZeus\SpatieTranslatable\Resources\Pages\CreateRecord\Concerns\Translatable;

trait CreatesTranslatedContent
{
    use Translatable {
        handleRecordCreation as protected createTranslatedRecord;
    }
    use ValidatesTranslatableLocales;

    protected function handleRecordCreation(array $data): Model
    {
        return $this->withTranslatedSlugGuard(fn () => $this->createTranslatedRecord($data));
    }
}
