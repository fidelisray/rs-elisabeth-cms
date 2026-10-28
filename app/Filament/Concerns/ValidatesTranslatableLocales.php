<?php

namespace App\Filament\Concerns;

use App\Support\TranslatedSlugIndexes;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait ValidatesTranslatableLocales
{
    /**
     * Hook yang dieksekusi sebelum validasi form Filament berjalan.
     * Mengisi slug otomatis jika judul/nama terisi namun slug masih kosong.
     */
    protected function beforeValidate(): void
    {
        $activeSlug = data_get($this, 'data.slug');
        $titleOrName = data_get($this, 'data.title') ?? data_get($this, 'data.name');

        if ($this->isTranslatableAttributeBlank($activeSlug) && filled($titleOrName)) {
            data_set($this, 'data.slug', Str::slug($titleOrName));
        }
    }

    /**
     * Hook yang dieksekusi setelah form aktif lolos validasi bawaan Filament.
     * Memastikan seluruh bahasa yang didukung (ID dan EN) terisi lengkap
     * pada setiap field yang required sebelum record disimpan ke database.
     */
    protected function afterValidate(): void
    {
        $this->validateTranslatableLocalesCompleteness();
    }

    /**
     * Memvalidasi kelengkapan data translatable untuk seluruh locale yang didukung.
     */
    protected function validateTranslatableLocalesCompleteness(): void
    {
        $resource = static::getResource();
        $locales = method_exists($resource, 'getTranslatableLocales')
            ? $resource::getTranslatableLocales()
            : config('app.supported_locales', ['id', 'en']);

        $translatableAttributes = method_exists($resource, 'getTranslatableAttributes')
            ? $resource::getTranslatableAttributes()
            : [];

        if (empty($translatableAttributes)) {
            return;
        }

        $requiredAttributes = $this->getRequiredTranslatableAttributes();
        $activeState = $this->form->getState();
        $rawState = method_exists($this->form, 'getRawState') ? $this->form->getRawState() : $activeState;

        // Simpan state aktif saat ini ke otherLocaleData agar data tidak hilang
        $this->otherLocaleData[$this->activeLocale] = Arr::only($activeState, $translatableAttributes);

        $missingByLocale = [];
        $slugErrors = [];

        foreach ($locales as $locale) {
            $localeData = $this->otherLocaleData[$locale] ?? [];

            // Auto-generate slug jika title/name ada tetapi slug kosong
            $titleOrName = $localeData['title'] ?? $localeData['name'] ?? null;
            if (filled($titleOrName) && $this->isTranslatableAttributeBlank($localeData['slug'] ?? null)) {
                $generatedSlug = Str::slug($titleOrName);
                $localeData['slug'] = $generatedSlug;
                $this->otherLocaleData[$locale]['slug'] = $generatedSlug;

                if ($locale === $this->activeLocale) {
                    $this->form->fill(['slug' => $generatedSlug]);
                }
            }

            // Periksa setiap field translatable yang required
            $missingInLocale = [];
            foreach ($requiredAttributes as $attribute) {
                $value = $localeData[$attribute] ?? null;

                if ($this->isTranslatableAttributeBlank($value)) {
                    $missingInLocale[] = $attribute;
                }
            }

            if (! empty($missingInLocale)) {
                $missingByLocale[$locale] = $missingInLocale;
            } else {
                // Jika slug ada, pastikan unik di database untuk locale ini
                $slug = $localeData['slug'] ?? null;
                if (filled($slug) && in_array('slug', $translatableAttributes, true)) {
                    $modelClass = static::getModel();
                    $record = method_exists($this, 'getRecord') ? $this->getRecord() : null;
                    $recordId = $record?->getKey();
                    $keyName = $record?->getKeyName() ?? (new $modelClass)->getKeyName();

                    $isDuplicate = $modelClass::withTrashed()->where("slug->{$locale}", $slug)
                        ->when($recordId, fn ($q) => $q->where($keyName, '!=', $recordId))
                        ->exists();

                    if ($isDuplicate) {
                        $slugErrors[$locale] = "Identifier (Slug) '{$slug}' sudah digunakan pada ".$this->getTranslatableLocaleLabel($locale).'.';
                    }
                }
            }
        }

        // Jika terdapat field yang belum lengkap atau terjadi duplikasi slug
        if (! empty($missingByLocale) || ! empty($slugErrors)) {
            $targetLocale = array_key_first($missingByLocale) ?? array_key_first($slugErrors);

            // Alihkan tab form ke bahasa yang belum terisi agar admin langsung diarahkan ke form yang perlu diisi
            if ($targetLocale && $targetLocale !== $this->activeLocale) {
                $this->activeLocale = $targetLocale;

                if (function_exists('filament') && filament('spatie-translatable')->getPersistLocale()) {
                    session()->put('spatie_translatable_active_locale', $targetLocale);
                }

                $record = method_exists($this, 'getRecord') ? $this->getRecord() : null;
                $recordAttributes = $record
                    ? Arr::except($record->attributesToArray(), $translatableAttributes)
                    : [];

                $this->form->fill([
                    ...$recordAttributes,
                    ...Arr::except($rawState, $translatableAttributes),
                    ...($this->otherLocaleData[$targetLocale] ?? []),
                ]);

                unset($this->otherLocaleData[$targetLocale]);
            }

            $messages = [];
            foreach ($missingByLocale as $loc => $fields) {
                $locLabel = $this->getTranslatableLocaleLabel($loc);
                $fieldLabels = array_map(fn ($f) => $this->getTranslatableAttributeLabel($f), $fields);
                $messages[] = "• {$locLabel}: ".implode(', ', $fieldLabels);
            }

            foreach ($slugErrors as $loc => $slugMsg) {
                $messages[] = "• {$slugMsg}";
            }

            $body = ($missingByLocale
                ? 'Semua field wajib diisi untuk seluruh bahasa (Indonesia & English) sebelum data dapat disimpan.'
                : 'Gunakan identifier (slug) yang belum dipakai pada bahasa terkait, termasuk oleh konten yang sudah dihapus.')
                ."\n\n".implode("\n", $messages);

            Notification::make()
                ->title($missingByLocale ? 'Data Multi-Bahasa Belum Lengkap' : 'Identifier (Slug) Sudah Digunakan')
                ->body($body)
                ->danger()
                ->persistent()
                ->send();

            $this->halt(shouldRollbackDatabaseTransaction: true);
        }

        // Bersihkan locale aktif dari otherLocaleData agar konsisten dengan Spatie translatable driver
        unset($this->otherLocaleData[$this->activeLocale]);
    }

    protected function withTranslatedSlugGuard(Closure $write): Model
    {
        try {
            return DB::transaction($write);
        } catch (QueryException $exception) {
            $locale = TranslatedSlugIndexes::conflictingLocale($exception, (new (static::getModel()))->getTable());

            if ($locale === null) {
                throw $exception;
            }

            // Rollback has completed before changing the form or issuing any further queries.
            if ($locale !== $this->activeLocale) {
                $attributes = static::getResource()::getTranslatableAttributes();
                $target = $this->otherLocaleData[$locale] ?? [];
                $this->otherLocaleData[$this->activeLocale] = Arr::only($this->data, $attributes);
                $this->activeLocale = $locale;

                $record = method_exists($this, 'getRecord') ? $this->getRecord() : null;
                $recordAttributes = $record
                    ? Arr::except($record->attributesToArray(), $attributes)
                    : [];

                $this->form->fill([
                    ...$recordAttributes,
                    ...Arr::except($this->data, $attributes),
                    ...$target,
                ]);
                unset($this->otherLocaleData[$locale]);

                if (filament('spatie-translatable')->getPersistLocale()) {
                    session()->put('spatie_translatable_active_locale', $locale);
                }
            }

            throw ValidationException::withMessages([
                'data.slug' => 'Identifier (Slug) sudah digunakan pada '.$this->getTranslatableLocaleLabel($locale).'. Pilih identifier lain.',
            ]);
        }
    }

    /**
     * Menentukan field translatable apa saja yang wajib diisi.
     * Default: seluruh atribut translatable pada modul adalah required.
     */
    protected function getRequiredTranslatableAttributes(): array
    {
        return array_values(array_filter(
            static::getResource()::getTranslatableAttributes(),
            function (string $attribute): bool {
                if (! in_array($attribute, [
                    'wa_link_text', 'wa_prefilled_message', 'whatsapp_text',
                    'bio', 'location', 'experience', 'education',
                    'description', 'category',
                ], true)) {
                    return true;
                }

                // Optional CTA or doctor marketing text may be absent; if provided in either locale, require both.
                return ! $this->isTranslatableAttributeBlank(data_get($this->data, $attribute))
                    || collect($this->otherLocaleData)->contains(
                        fn ($state): bool => ! $this->isTranslatableAttributeBlank($state[$attribute] ?? null)
                    );
            }
        ));
    }

    /**
     * Memeriksa apakah nilai field translatable kosong, termasuk RichEditor kosong.
     */
    protected function isTranslatableAttributeBlank(mixed $value): bool
    {
        if (is_null($value)) {
            return true;
        }

        if (is_string($value)) {
            $stripped = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $cleaned = trim(str_replace("\xc2\xa0", ' ', $stripped));

            return $cleaned === '';
        }

        if (is_array($value)) {
            return empty($value);
        }

        return blank($value);
    }

    /**
     * Label human-readable untuk atribut translatable.
     */
    protected function getTranslatableAttributeLabel(string $attribute): string
    {
        return match ($attribute) {
            'title' => 'Judul',
            'name' => 'Nama',
            'slug' => 'Identifier (Slug)',
            'shorts' => 'Ringkasan',
            'short_description' => 'Deskripsi Singkat',
            'content' => 'Konten',
            'description' => 'Deskripsi',
            'tagline' => 'Tagline / Highlight',
            'category', 'tags' => 'Kategori',
            'wa_link_text' => 'Label Tombol WhatsApp',
            'wa_prefilled_message' => 'Pesan Otomatis WhatsApp',
            'whatsapp_text' => 'Pesan WhatsApp Kamar',
            'bio' => 'Biografi / Tentang Dokter',
            'location' => 'Data Lokasi Praktik',
            'experience' => 'Data Pengalaman',
            'education' => 'Data Riwayat Pendidikan',
            default => ucfirst(str_replace('_', ' ', $attribute)),
        };
    }

    /**
     * Label human-readable untuk locale.
     */
    protected function getTranslatableLocaleLabel(string $locale): string
    {
        return match ($locale) {
            'id' => 'Bahasa Indonesia (ID)',
            'en' => 'English (EN)',
            default => strtoupper($locale),
        };
    }
}
