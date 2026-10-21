<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BilingualJsonConstraints
{
    /**
     * Required fields must contain two non-empty strings; optional fields may be SQL NULL.
     * Identifiers come exclusively from the base migrations, not from request input.
     */
    public static function add(string $table, array $required, array $optional = []): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ([$table, ...$required, ...$optional] as $identifier) {
            if (! preg_match('/^[a-z_][a-z0-9_]*$/D', $identifier)) {
                throw new InvalidArgumentException('Invalid translation constraint identifier.');
            }
        }

        foreach ([...$required, ...$optional] as $column) {
            $field = '"'.$column.'"';
            $conditions = ["jsonb_typeof({$field}) = 'object'"];

            foreach (['id', 'en'] as $locale) {
                $conditions[] = "jsonb_typeof({$field}->'{$locale}') = 'string'";
                $conditions[] = "length(btrim({$field}->>'{$locale}', E' \\t\\n\\r')) > 0";
            }

            // PostgreSQL CHECK accepts UNKNOWN, so IS TRUE also rejects missing/JSON-null values.
            $check = '(('.implode(' AND ', $conditions).') IS TRUE)';

            if (in_array($column, $optional, true)) {
                $check = "({$field} IS NULL OR {$check})";
            }

            DB::statement('ALTER TABLE "'.$table.'" ADD CONSTRAINT "'.$table.'_'.$column.'_translations_check" CHECK '.$check);
        }
    }
}
