<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;

trait EditsTranslatedContent
{
    use Translatable {
        handleRecordUpdate as protected updateTranslatedRecord;
    }
    use ValidatesTranslatableLocales;

    /**
     * Mempertahankan nilai field non-translatable (termasuk field Read-Only / dehydrated(false)
     * seperti identitas resmi dokter dari SIMRS) ketika pengguna mengganti bahasa di LocaleSwitcher.
     */
    public function updatedActiveLocale(): void
    {
        if (filament('spatie-translatable')->getPersistLocale()) {
            session()->put('spatie_translatable_active_locale', $this->activeLocale);
        }

        if (blank($this->oldActiveLocale)) {
            return;
        }

        $this->resetValidation();

        $translatableAttributes = static::getResource()::getTranslatableAttributes();

        try {
            $rawState = method_exists($this->form, 'getRawState')
                ? $this->form->getRawState()
                : $this->form->getState();

            $this->otherLocaleData[$this->oldActiveLocale] = Arr::only(
                $rawState,
                $translatableAttributes
            );

            $record = method_exists($this, 'getRecord') ? $this->getRecord() : null;
            $recordAttributes = $record
                ? Arr::except($record->attributesToArray(), $translatableAttributes)
                : [];

            $this->form->fill([
                ...$recordAttributes,
                ...Arr::except($rawState, $translatableAttributes),
                ...($this->otherLocaleData[$this->activeLocale] ?? []),
            ]);

            unset($this->otherLocaleData[$this->activeLocale]);
        } catch (ValidationException $e) {
            $this->activeLocale = $this->oldActiveLocale;

            throw $e;
        }
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->withTranslatedSlugGuard(fn () => $this->updateTranslatedRecord($record, $data));
    }
}
