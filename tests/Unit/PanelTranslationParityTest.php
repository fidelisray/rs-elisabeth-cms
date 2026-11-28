<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class PanelTranslationParityTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const SCAN_PATHS = [
        'app/Filament',
        'app/Enums',
        'app/Rules',
        'app/Http/Controllers/Admin',
        'app/Http/Middleware/SetAdminPanelLocale.php',
        'app/Providers/Filament',
        'app/Livewire',
        'app/Services/DynamicForm/DynamicFormLogicEvaluator.php',
        'resources/views/filament',
    ];

    /**
     * @var list<string>
     */
    private const VENDOR_KEY_PREFIXES = [
        'filament-',
        'validation.',
        'auth.',
        'passwords.',
        'pagination.',
    ];

    public function test_translation_json_files_are_valid_utf8_without_duplicates_and_sorted(): void
    {
        foreach (['id', 'en'] as $locale) {
            $path = base_path("lang/{$locale}.json");
            $this->assertFileExists($path, "Missing translation dictionary [{$path}].");

            $raw = file_get_contents($path);
            $this->assertIsString($raw);
            $this->assertTrue(mb_check_encoding($raw, 'UTF-8'), "Dictionary [{$locale}.json] must be valid UTF-8.");

            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $this->assertIsArray($decoded);
            $this->assertNotEmpty($decoded);

            // Detect duplicate top-level JSON keys before PHP overwrites them during json_decode.
            preg_match_all('/^\s{4}"((?:\\\\.|[^"\\\\])*)"\s*:/m', $raw, $keyMatches);
            $rawKeys = array_map(
                static fn (string $encoded): string => (string) json_decode('"'.$encoded.'"', true, 512, JSON_THROW_ON_ERROR),
                $keyMatches[1]
            );
            $this->assertSame(
                count(array_unique($rawKeys)),
                count($rawKeys),
                "Duplicate JSON key detected in lang/{$locale}.json."
            );

            $keys = array_keys($decoded);
            $sorted = $keys;
            sort($sorted, SORT_STRING);
            $this->assertSame($sorted, $keys, "Keys in lang/{$locale}.json must be sorted alphabetically (SORT_STRING).");
        }
    }

    public function test_id_and_en_dictionaries_have_full_key_value_and_placeholder_parity(): void
    {
        $id = $this->loadDictionary('id');
        $en = $this->loadDictionary('en');

        $missingInEn = array_values(array_diff(array_keys($id), array_keys($en)));
        $missingInId = array_values(array_diff(array_keys($en), array_keys($id)));

        $this->assertSame([], $missingInEn, 'Keys present in lang/id.json but missing in lang/en.json: '.implode(', ', $missingInEn));
        $this->assertSame([], $missingInId, 'Keys present in lang/en.json but missing in lang/id.json: '.implode(', ', $missingInId));

        foreach ($id as $key => $idValue) {
            $enValue = $en[$key];

            $this->assertIsString($idValue, "Value for [{$key}] in lang/id.json must be a string.");
            $this->assertIsString($enValue, "Value for [{$key}] in lang/en.json must be a string.");
            $this->assertNotSame('', trim($idValue), "Empty translation value for [{$key}] in lang/id.json.");
            $this->assertNotSame('', trim($enValue), "Empty translation value for [{$key}] in lang/en.json.");

            $idPlaceholders = $this->extractPlaceholders($idValue);
            $enPlaceholders = $this->extractPlaceholders($enValue);

            $this->assertSame(
                $idPlaceholders,
                $enPlaceholders,
                "Placeholder mismatch for key [{$key}]: ID=".implode(',', $idPlaceholders).' vs EN='.implode(',', $enPlaceholders)
            );
        }
    }

    public function test_every_panel_translation_call_matches_dictionaries_with_zero_missing_or_orphaned_keys(): void
    {
        $id = $this->loadDictionary('id');
        $en = $this->loadDictionary('en');
        $usedKeys = $this->extractPanelTranslationKeys();

        $this->assertNotEmpty($usedKeys, 'Expected panel source scan to discover translation keys.');

        $missingFromId = array_values(array_diff($usedKeys, array_keys($id)));
        $missingFromEn = array_values(array_diff($usedKeys, array_keys($en)));
        $orphanedInId = array_values(array_diff(array_keys($id), $usedKeys));
        $orphanedInEn = array_values(array_diff(array_keys($en), $usedKeys));

        $this->assertSame([], $missingFromId, 'Panel keys missing in lang/id.json: '.implode(' | ', $missingFromId));
        $this->assertSame([], $missingFromEn, 'Panel keys missing in lang/en.json: '.implode(' | ', $missingFromEn));
        $this->assertSame([], $orphanedInId, 'Orphaned keys in lang/id.json not referenced by panel: '.implode(' | ', $orphanedInId));
        $this->assertSame([], $orphanedInEn, 'Orphaned keys in lang/en.json not referenced by panel: '.implode(' | ', $orphanedInEn));
    }

    /**
     * @return array<string, string>
     */
    private function loadDictionary(string $locale): array
    {
        $raw = (string) file_get_contents(base_path("lang/{$locale}.json"));

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<string>
     */
    private function extractPlaceholders(string $text): array
    {
        preg_match_all('/(?<![A-Za-z0-9_]):([a-z][a-z0-9_]*)/', $text, $matches);
        $placeholders = array_values(array_unique($matches[1]));
        sort($placeholders, SORT_STRING);

        return $placeholders;
    }

    /**
     * @return list<string>
     */
    private function extractPanelTranslationKeys(): array
    {
        $files = [];

        foreach (self::SCAN_PATHS as $relativePath) {
            $fullPath = base_path($relativePath);

            if (is_file($fullPath)) {
                $files[] = $fullPath;

                continue;
            }

            if (! is_dir($fullPath)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fullPath));

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        $keys = [];
        $pattern = "/(?:__|self::t)\(\s*(?:'((?:\\\\'|[^'])*)'|\"((?:\\\\\"|[^\"])*)\")\s*(?:,|\))/s";

        foreach ($files as $file) {
            $content = (string) file_get_contents($file);

            if (! preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $raw = ($match[1] ?? '') !== ''
                    ? str_replace(["\\'", '\\\\'], ["'", '\\'], $match[1])
                    : str_replace(['\"', '\\\\'], ['"', '\\'], $match[2] ?? '');

                $isVendorKey = false;
                foreach (self::VENDOR_KEY_PREFIXES as $prefix) {
                    if (str_starts_with($raw, $prefix)) {
                        $isVendorKey = true;
                        break;
                    }
                }

                if ($isVendorKey) {
                    continue;
                }

                $keys[$raw] = true;
            }
        }

        $list = array_keys($keys);
        sort($list, SORT_STRING);

        return $list;
    }
}
