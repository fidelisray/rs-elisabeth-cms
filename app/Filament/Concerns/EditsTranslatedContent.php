<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;

trait EditsTranslatedContent
{
    use Translatable {
        handleRecordUpdate as protected updateTranslatedRecord;
    }
    use ValidatesTranslatableLocales;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->withTranslatedSlugGuard(fn () => $this->updateTranslatedRecord($record, $data));
    }
}
