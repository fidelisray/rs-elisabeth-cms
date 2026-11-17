<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

trait CreatesTranslatedContent
{
    use ValidatesTranslatableLocales;

    protected function handleRecordCreation(array $data): Model
    {
        return $this->withTranslatedSlugGuard(function () use ($data): Model {
            $record = new ($this->getModel())();
            $this->applyTranslatedAttributes($record, $data);

            if ($parentRecord = $this->getParentRecord()) {
                return $this->associateRecordWithParent($record, $parentRecord);
            }

            $record->save();

            return $record;
        });
    }
}
