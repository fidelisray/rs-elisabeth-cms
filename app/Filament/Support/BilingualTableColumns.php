<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Doctor;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * Helper DRY untuk indikator visual kelengkapan bilingual (ID · EN)
 * pada 7 tabel konten dan progres 4 field marketing pada tabel Dokter.
 */
final class BilingualTableColumns
{
    /**
     * Kolom badge status kelengkapan bilingual (field wajib & opsional) untuk 7 tabel konten.
     *
     * @param  list<string>  $requiredFields
     * @param  list<string>  $optionalFields
     */
    public static function contentLanguageBadge(array $requiredFields, array $optionalFields = []): TextColumn
    {
        return TextColumn::make('bilingual_status')
            ->label(__('Bahasa'))
            ->badge()
            ->alignCenter()
            ->getStateUsing(function (Model $record) use ($requiredFields, $optionalFields): string {
                $status = self::evaluateContentTranslations($record, $requiredFields, $optionalFields);

                return match ($status['state']) {
                    'complete' => __('ID · EN'),
                    'partial' => __('ID · EN (Parsial)'),
                    default => __('ID / EN Kurang'),
                };
            })
            ->color(function (Model $record) use ($requiredFields, $optionalFields): string {
                $status = self::evaluateContentTranslations($record, $requiredFields, $optionalFields);

                return match ($status['state']) {
                    'complete' => 'success',
                    'partial' => 'warning',
                    default => 'danger',
                };
            })
            ->tooltip(function (Model $record) use ($requiredFields, $optionalFields): string {
                $status = self::evaluateContentTranslations($record, $requiredFields, $optionalFields);

                if ($status['state'] === 'complete') {
                    return $optionalFields === []
                        ? __('Seluruh konten wajib lengkap dalam ID & EN.')
                        : __('Seluruh konten wajib dan opsional lengkap dalam ID & EN.');
                }

                if ($status['state'] === 'partial') {
                    return __('Field opsional belum lengkap: :fields', [
                        'fields' => implode(', ', $status['missing_optional']),
                    ]);
                }

                return __('Field wajib belum lengkap: :fields', [
                    'fields' => implode(', ', $status['missing_required']),
                ]);
            });
    }

    /**
     * Kolom badge progres 4 field marketing bilingual (ID x/4 · EN y/4) pada tabel Dokter.
     */
    public static function doctorBilingualProgress(): TextColumn
    {
        return TextColumn::make('is_profile_complete')
            ->label(__('Kelengkapan Profil'))
            ->badge()
            ->alignCenter()
            ->getStateUsing(function (Doctor $record): string {
                $stats = self::evaluateDoctorProfile($record);

                return "ID {$stats['id_filled']}/4 · EN {$stats['en_filled']}/4";
            })
            ->color(function (Doctor $record): string {
                $stats = self::evaluateDoctorProfile($record);

                if ($stats['id_filled'] === 4 && $stats['en_filled'] === 4) {
                    return 'success';
                }

                if ($stats['id_filled'] === 0 && $stats['en_filled'] === 0) {
                    return 'gray';
                }

                return 'warning';
            })
            ->tooltip(function (Doctor $record): string {
                $stats = self::evaluateDoctorProfile($record);

                if ($stats['id_filled'] === 4 && $stats['en_filled'] === 4) {
                    return __('Profil 4 data marketing lengkap (ID & EN)');
                }

                $parts = [];
                if ($stats['missing_id'] !== []) {
                    $parts[] = 'ID: '.implode(', ', $stats['missing_id']);
                }
                if ($stats['missing_en'] !== []) {
                    $parts[] = 'EN: '.implode(', ', $stats['missing_en']);
                }

                return __('Belum lengkap — :details', ['details' => implode(' · ', $parts)]);
            });
    }

    /**
     * @param  list<string>  $requiredFields
     * @param  list<string>  $optionalFields
     * @return array{state: 'complete'|'partial'|'incomplete', missing_required: list<string>, missing_optional: list<string>}
     */
    public static function evaluateContentTranslations(Model $record, array $requiredFields, array $optionalFields = []): array
    {
        $missingRequired = [];
        foreach ($requiredFields as $field) {
            $missingLocales = self::missingLocalesForField($record, $field);
            if ($missingLocales !== []) {
                $missingRequired[] = self::fieldLabel($field).' ('.implode('/', $missingLocales).')';
            }
        }

        if ($missingRequired !== []) {
            return [
                'state' => 'incomplete',
                'missing_required' => $missingRequired,
                'missing_optional' => [],
            ];
        }

        $missingOptional = [];
        foreach ($optionalFields as $field) {
            $missingLocales = self::missingLocalesForField($record, $field);
            if ($missingLocales !== []) {
                $missingOptional[] = self::fieldLabel($field).' ('.implode('/', $missingLocales).')';
            }
        }

        return [
            'state' => $missingOptional === [] ? 'complete' : 'partial',
            'missing_required' => [],
            'missing_optional' => $missingOptional,
        ];
    }

    /**
     * @return array{id_filled: int, en_filled: int, missing_id: list<string>, missing_en: list<string>}
     */
    public static function evaluateDoctorProfile(Doctor $record): array
    {
        $fields = ['bio', 'location', 'experience', 'education'];
        $idFilled = 0;
        $enFilled = 0;
        $missingId = [];
        $missingEn = [];

        foreach ($fields as $field) {
            if (self::hasTranslatedText($record, $field, 'id')) {
                $idFilled++;
            } else {
                $missingId[] = self::fieldLabel($field);
            }

            if (self::hasTranslatedText($record, $field, 'en')) {
                $enFilled++;
            } else {
                $missingEn[] = self::fieldLabel($field);
            }
        }

        return [
            'id_filled' => $idFilled,
            'en_filled' => $enFilled,
            'missing_id' => $missingId,
            'missing_en' => $missingEn,
        ];
    }

    /**
     * @return list<string>
     */
    private static function missingLocalesForField(Model $record, string $field): array
    {
        $missing = [];

        foreach (['id' => 'ID', 'en' => 'EN'] as $locale => $label) {
            if (! self::hasTranslatedText($record, $field, $locale)) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    private static function hasTranslatedText(Model $record, string $field, string $locale): bool
    {
        if (! method_exists($record, 'getTranslation')) {
            return filled($record->getAttribute($field));
        }

        $raw = (string) $record->getTranslation($field, $locale, false);
        $plain = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\xc2\xa0", ' ', $plain)) !== '';
    }

    private static function fieldLabel(string $field): string
    {
        return match ($field) {
            'title' => __('Judul'),
            'name' => __('Nama'),
            'slug' => __('Identifier'),
            'shorts' => __('Ringkasan'),
            'short_description' => __('Deskripsi Singkat'),
            'description' => __('Deskripsi'),
            'content' => __('Konten'),
            'tags', 'category' => __('Kategori'),
            'tagline' => __('Tagline'),
            'wa_link_text' => __('Label Tombol WhatsApp'),
            'wa_prefilled_message', 'whatsapp_text' => __('Pesan Otomatis WhatsApp'),
            'bio' => __('Bio'),
            'location' => __('Lokasi'),
            'experience' => __('Pengalaman'),
            'education' => __('Pendidikan'),
            default => $field,
        };
    }
}
