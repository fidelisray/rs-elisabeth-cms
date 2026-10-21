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
     * Scope pencarian berdasarkan slug multi-bahasa dengan Smart Fallback:
     * memeriksa locale aktif terlebih dahulu, lalu memeriksa locale pendukung lainnya.
     */
    public function scopeWhereTranslatedSlug(Builder $query, string $slug, ?string $locale = null): Builder
    {
        $locale ??= app()->getLocale();
        $supportedLocales = config('app.supported_locales', ['id', 'en']);
        $localesToSearch = array_values(array_unique(array_merge([$locale, 'id'], $supportedLocales)));

        return $query->where(function (Builder $q) use ($slug, $localesToSearch) {
            foreach ($localesToSearch as $loc) {
                $q->orWhere("slug->{$loc}", $slug);
            }
        });
    }

    /**
     * Kembalikan daftar slug untuk setiap bahasa yang didukung (dengan fallback ke 'id'),
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
            $slug = $this->getTranslation('slug', $locale, useFallbackLocale: true);
            if (filled($slug)) {
                $slugs[$locale] = (string) $slug;
            }
        }

        return $slugs;
    }
}
