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
    public ?string $activeLocale = 'id';

    /**
     * Daftar locale yang didukung pada modul translatable.
     *
     * @return array<int, string>
     */
    protected function getSupportedLocales(): array
    {
        $resource = static::getResource();

        return method_exists($resource, 'getTranslatableLocales')
            ? $resource::getTranslatableLocales()
            : config('app.supported_locales', ['id', 'en']);
    }

    /**
     * Mengisi slug otomatis per bahasa sebelum validasi form Filament berjalan
     * apabila judul/nama bahasa tersebut sudah diisi namun slug masih kosong.
     */
    protected function beforeValidate(): void
    {
        foreach ($this->getSupportedLocales() as $locale) {
            $slug = data_get($this, "data.slug.{$locale}");
            $titleOrName = data_get($this, "data.title.{$locale}") ?? data_get($this, "data.name.{$locale}");

            if ($this->isTranslatableAttributeBlank($slug) && filled($titleOrName)) {
                data_set($this, "data.slug.{$locale}", Str::slug($titleOrName));
            }
        }
    }

    /**
     * Memastikan seluruh bahasa yang didukung (ID dan EN) terisi lengkap
     * dan memiliki slug unik sebelum record disimpan ke database.
     */
    protected function afterValidate(): void
    {
        $this->validateTranslatableLocalesCompleteness();
    }

    protected function onValidationError(ValidationException $exception): void
    {
        $resource = static::getResource();
        $translatableAttributes = method_exists($resource, 'getTranslatableAttributes')
            ? $resource::getTranslatableAttributes()
            : [];

        foreach (array_keys($exception->errors()) as $errorKey) {
            foreach ($translatableAttributes as $attribute) {
                if (str_starts_with($errorKey, "data.{$attribute}.")) {
                    Notification::make()
                        ->title(__('Data Multi-Bahasa Belum Lengkap'))
                        ->body(__('Semua field wajib diisi untuk seluruh bahasa (Indonesia & English) sebelum data dapat disimpan.'))
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }
            }
        }
    }

    /**
     * Memvalidasi kelengkapan data translatable untuk seluruh locale yang didukung.
     */
    protected function validateTranslatableLocalesCompleteness(): void
    {
        $resource = static::getResource();
        $locales = $this->getSupportedLocales();

        $translatableAttributes = method_exists($resource, 'getTranslatableAttributes')
            ? $resource::getTranslatableAttributes()
            : [];

        if (empty($translatableAttributes)) {
            return;
        }

        $requiredAttributes = $this->getRequiredTranslatableAttributes();
        $activeState = $this->form->getState();

        $missingByLocale = [];
        $slugErrors = [];

        foreach ($locales as $locale) {
            $titleOrName = data_get($activeState, "title.{$locale}")
                ?? data_get($activeState, "name.{$locale}")
                ?? data_get($this->data, "title.{$locale}")
                ?? data_get($this->data, "name.{$locale}");

            $currentSlug = data_get($activeState, "slug.{$locale}") ?? data_get($this->data, "slug.{$locale}");

            if (filled($titleOrName) && $this->isTranslatableAttributeBlank($currentSlug) && in_array('slug', $translatableAttributes, true)) {
                $generatedSlug = Str::slug($titleOrName);
                data_set($activeState, "slug.{$locale}", $generatedSlug);
                data_set($this->data, "slug.{$locale}", $generatedSlug);
                $currentSlug = $generatedSlug;
            }

            $missingInLocale = [];
            foreach ($requiredAttributes as $attribute) {
                $value = data_get($activeState, "{$attribute}.{$locale}") ?? data_get($this->data, "{$attribute}.{$locale}");

                if ($this->isTranslatableAttributeBlank($value)) {
                    $missingInLocale[] = $attribute;
                    $this->addError(
                        "data.{$attribute}.{$locale}",
                        __(':field wajib diisi untuk :locale.', [
                            'field' => $this->getTranslatableAttributeLabel($attribute),
                            'locale' => $this->getTranslatableLocaleLabel($locale),
                        ])
                    );
                }
            }

            if (! empty($missingInLocale)) {
                $missingByLocale[$locale] = $missingInLocale;
            } elseif (filled($currentSlug) && in_array('slug', $translatableAttributes, true)) {
                $modelClass = static::getModel();
                $record = method_exists($this, 'getRecord') ? $this->getRecord() : null;
                $recordId = $record?->getKey();
                $keyName = $record?->getKeyName() ?? (new $modelClass)->getKeyName();

                $isDuplicate = $modelClass::withTrashed()->where("slug->{$locale}", $currentSlug)
                    ->when($recordId, fn ($q) => $q->where($keyName, '!=', $recordId))
                    ->exists();

                if ($isDuplicate) {
                    $slugErrors[$locale] = __("Identifier (Slug) ':slug' sudah digunakan pada :locale.", [
                        'slug' => $currentSlug,
                        'locale' => $this->getTranslatableLocaleLabel($locale),
                    ]);
                    $this->addError(
                        "data.slug.{$locale}",
                        __('Identifier (Slug) sudah digunakan. Pilih identifier lain.')
                    );
                }
            }
        }

        if (! empty($missingByLocale) || ! empty($slugErrors)) {
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
                ? __('Semua field wajib diisi untuk seluruh bahasa (Indonesia & English) sebelum data dapat disimpan.')
                : __('Gunakan identifier (slug) yang belum dipakai pada bahasa terkait, termasuk oleh konten yang sudah dihapus.'))
                ."\n\n".implode("\n", $messages);

            Notification::make()
                ->title($missingByLocale ? __('Data Multi-Bahasa Belum Lengkap') : __('Identifier (Slug) Sudah Digunakan'))
                ->body($body)
                ->danger()
                ->persistent()
                ->send();

            $this->halt(shouldRollbackDatabaseTransaction: true);
        }
    }

    /**
     * Menyimpan seluruh atribut non-translatable dan translatable (ID & EN) ke model.
     *
     * @param  array<string, mixed>  $data
     */
    protected function applyTranslatedAttributes(Model $record, array $data): void
    {
        $resource = static::getResource();
        $translatableAttributes = method_exists($resource, 'getTranslatableAttributes')
            ? $resource::getTranslatableAttributes()
            : [];
        $locales = $this->getSupportedLocales();

        $record->fill(Arr::except($data, $translatableAttributes));

        foreach ($translatableAttributes as $attribute) {
            if (! array_key_exists($attribute, $data)) {
                continue;
            }

            $value = $data[$attribute];

            if (is_array($value)) {
                foreach ($locales as $locale) {
                    if (array_key_exists($locale, $value)) {
                        $record->setTranslation($attribute, $locale, $value[$locale]);
                    }
                }
            } else {
                $record->setTranslation($attribute, $this->activeLocale ?? 'id', $value);
            }
        }
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

            $message = __('Identifier (Slug) sudah digunakan pada :locale. Pilih identifier lain.', [
                'locale' => $this->getTranslatableLocaleLabel($locale),
            ]);

            throw ValidationException::withMessages([
                "data.slug.{$locale}" => $message,
            ]);
        }
    }

    /**
     * Menentukan field translatable apa saja yang wajib diisi.
     * Default: seluruh atribut translatable pada modul adalah required,
     * kecuali atribut opsional yang baru wajib diisi keduanya bila salah satu bahasa diisi.
     *
     * @return array<int, string>
     */
    protected function getRequiredTranslatableAttributes(): array
    {
        $locales = $this->getSupportedLocales();

        return array_values(array_filter(
            static::getResource()::getTranslatableAttributes(),
            function (string $attribute) use ($locales): bool {
                if (! in_array($attribute, [
                    'wa_link_text', 'wa_prefilled_message', 'whatsapp_text',
                    'bio', 'location', 'experience', 'education',
                    'description', 'category',
                ], true)) {
                    return true;
                }

                foreach ($locales as $locale) {
                    if (! $this->isTranslatableAttributeBlank(data_get($this->data, "{$attribute}.{$locale}"))) {
                        return true;
                    }
                }

                return false;
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
            foreach ($value as $item) {
                if (! $this->isTranslatableAttributeBlank($item)) {
                    return false;
                }
            }

            return true;
        }

        return blank($value);
    }

    /**
     * Label human-readable untuk atribut translatable.
     */
    protected function getTranslatableAttributeLabel(string $attribute): string
    {
        return match ($attribute) {
            'title' => __('Judul'),
            'name' => __('Nama'),
            'slug' => __('Identifier (Slug)'),
            'shorts' => __('Ringkasan'),
            'short_description' => __('Deskripsi Singkat'),
            'content' => __('Konten'),
            'description' => __('Deskripsi'),
            'tagline' => __('Tagline / Highlight'),
            'category', 'tags' => __('Kategori'),
            'wa_link_text' => __('Label Tombol WhatsApp'),
            'wa_prefilled_message' => __('Pesan Otomatis WhatsApp'),
            'whatsapp_text' => __('Pesan WhatsApp Kamar'),
            'bio' => __('Biografi / Tentang Dokter'),
            'location' => __('Data Lokasi Praktik'),
            'experience' => __('Data Pengalaman'),
            'education' => __('Data Riwayat Pendidikan'),
            default => ucfirst(str_replace('_', ' ', $attribute)),
        };
    }

    /**
     * Label human-readable untuk locale.
     */
    protected function getTranslatableLocaleLabel(string $locale): string
    {
        return match ($locale) {
            'id' => __('Bahasa Indonesia (ID)'),
            'en' => __('Bahasa Inggris (EN)'),
            default => strtoupper($locale),
        };
    }
}
