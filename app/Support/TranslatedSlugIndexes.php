<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TranslatedSlugIndexes
{
    private const TABLES = ['news', 'articles', 'promotions', 'facility_services', 'room_facilities', 'banner_promotions'];

    public static function add(string $table): void
    {
        if (! in_array($table, self::TABLES, true)) {
            throw new InvalidArgumentException('Unsupported translated slug table.');
        }

        if (! in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            return;
        }

        foreach (['id', 'en'] as $locale) {
            $expression = DB::connection()->getQueryGrammar()->wrap('slug->'.$locale);
            $index = $table.'_slug_'.$locale.'_unique';
            DB::statement('CREATE UNIQUE INDEX "'.$index.'" ON "'.$table.'" (('.$expression.'))');
        }
    }

    public static function conflictingLocale(QueryException $exception, string $table): ?string
    {
        if (! in_array($table, self::TABLES, true) || ! in_array($exception->errorInfo[0] ?? null, ['23505', '23000'], true)) {
            return null;
        }

        // Only inspect the native diagnostic, never the SQL string or user-supplied bindings.
        $diagnostic = $exception->errorInfo[2] ?? '';

        foreach (['id', 'en'] as $locale) {
            $index = $table.'_slug_'.$locale.'_unique';

            if (str_contains($diagnostic, 'unique constraint "'.$index.'"')
                || str_contains($diagnostic, "UNIQUE constraint failed: index '{$index}'")) {
                return $locale;
            }
        }

        return null;
    }
}
