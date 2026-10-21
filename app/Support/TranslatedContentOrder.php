<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class TranslatedContentOrder
{
    public static function locale(mixed $locale = null): string
    {
        return is_string($locale) && in_array($locale, config('app.supported_locales', ['id', 'en']), true) ? $locale : 'id';
    }

    public static function apply(Builder $query, string $attribute, mixed $locale = null, string $direction = 'asc'): Builder
    {
        if (! in_array($attribute, ['name', 'title', 'slug'], true) || ! $query->getModel()->isTranslatableAttribute($attribute)) {
            throw new InvalidArgumentException('Unsupported translated sort attribute.');
        }

        $locale = self::locale($locale);

        return $query->orderBy($query->getModel()->qualifyColumn($attribute).'->'.$locale, $direction);
    }

    public static function column(string $attribute): Closure
    {
        return static function (Builder $query, string $direction, $livewire) use ($attribute): Builder {
            $locale = method_exists($livewire, 'getActiveTableLocale') ? $livewire->getActiveTableLocale() : null;

            return self::apply($query, $attribute, $locale, $direction);
        };
    }
}
