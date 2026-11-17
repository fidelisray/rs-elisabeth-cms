<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

trait EditsTranslatedContent
{
    use ValidatesTranslatableLocales;

    protected function fillForm(): void
    {
        $record = $this->getRecord();
        $resource = static::getResource();
        $translatableAttributes = method_exists($resource, 'getTranslatableAttributes')
            ? $resource::getTranslatableAttributes()
            : [];
        $locales = $this->getSupportedLocales();

        $translatedData = [];
        foreach ($translatableAttributes as $attribute) {
            $translatedData[$attribute] = [];
            foreach ($locales as $locale) {
                $translatedData[$attribute][$locale] = $record->getTranslation(
                    $attribute,
                    $locale,
                    useFallbackLocale: false
                );
            }
        }

        $this->fillFormWithDataAndCallHooks($record, $translatedData);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->withTranslatedSlugGuard(function () use ($record, $data): Model {
            $this->applyTranslatedAttributes($record, $data);
            $record->save();

            return $record;
        });
    }
}
