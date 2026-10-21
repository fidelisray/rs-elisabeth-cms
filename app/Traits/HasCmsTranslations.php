<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

trait HasCmsTranslations
{
    use HasTranslations {
        setTranslation as protected spatieSetTranslation;
    }

    /**
     * Set nilai terjemahan untuk locale tertentu.
     * Bila nilai kosong/null, hapus key locale tersebut agar tidak mengotori JSON
     * dan supaya mekanisme fallback ke bahasa utama ('id') tetap bekerja otomatis.
     */
    public function setTranslation(string $key, string $locale, $value): self
    {
        if ($value === null || $value === '') {
            $this->forgetTranslation($key, $locale);

            if ($this->getTranslations($key) === []) {
                $this->attributes[$key] = null;
            }

            return $this;
        }

        return $this->spatieSetTranslation($key, $locale, $value);
    }

    /**
     * Auto-generate slug per bahasa dari atribut sumber (name/title) bila slug untuk
     * bahasa tersebut masih kosong.
     */
    public function ensureTranslatedSlugsFrom(string $sourceAttribute): void
    {
        if (! $this->isTranslatableAttribute('slug') || ! $this->isTranslatableAttribute($sourceAttribute)) {
            return;
        }

        foreach ($this->getTranslatedLocales($sourceAttribute) as $locale) {
            $currentSlug = $this->getTranslationWithoutFallback('slug', $locale);
            if (blank($currentSlug)) {
                $sourceValue = (string) $this->getTranslationWithoutFallback($sourceAttribute, $locale);
                if (filled($sourceValue)) {
                    $this->spatieSetTranslation('slug', $locale, Str::slug($sourceValue));
                }
            }
        }
    }

    /**
     * Cari slug hanya pada locale yang diminta; slug bahasa lain bukan alias URL.
     */
    public function scopeWhereTranslatedSlug(Builder $query, string $slug, ?string $locale = null): Builder
    {
        $locale ??= app()->getLocale();

        if (! in_array($locale, config('app.supported_locales', ['id', 'en']), true)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where("slug->{$locale}", $slug);
    }

    /**
     * Kembalikan slug asli untuk setiap bahasa yang didukung, tanpa fallback,
     * digunakan oleh Web Frontend untuk mengarahkan language switcher pada halaman detail.
     *
     * @return array<string, string>
     */
    public function getAlternateSlugs(): array
    {
        if (! $this->isTranslatableAttribute('slug')) {
            return [];
        }

        $supportedLocales = config('app.supported_locales', ['id', 'en']);
        $slugs = [];

        foreach ($supportedLocales as $locale) {
            $slug = $this->getTranslationWithoutFallback('slug', $locale);
            if (filled($slug)) {
                $slugs[$locale] = (string) $slug;
            }
        }

        return $slugs;
    }
}
