<?php

namespace Tests\Unit;

use App\Enums\DynamicFormFieldType;
use App\Services\DynamicForm\DynamicFormLogicEvaluator;
use PHPUnit\Framework\TestCase;

class DynamicFormLogicEvaluatorTest extends TestCase
{
    private DynamicFormLogicEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new DynamicFormLogicEvaluator;
    }

    public function test_evaluates_all_visibility_operators_deterministically(): void
    {
        $operators = [
            ['equals', 'igd', 'igd', true],
            ['equals', 'igd', 'rawat_jalan', false],
            ['not_equals', 'igd', 'rawat_jalan', true],
            ['not_equals', 'igd', null, false],
            ['in', 'igd,mcu', 'mcu', true],
            ['not_in', 'igd,mcu', 'rawat_inap', true],
            ['contains', 'antri', 'Waktu antri cukup cepat', true],
            ['contains', 'bpjs', ['selected' => ['bpjs', 'mandiri']], true],
            ['gt', '3', 4, true],
            ['gte', '4', 4, true],
            ['lt', '3', 2, true],
            ['lte', '2', 2, true],
            ['is_empty', null, '', true],
            ['is_not_empty', null, 'terisi', true],
        ];

        foreach ($operators as [$op, $expected, $actual, $shouldBeVisible]) {
            $field = [
                'field_key' => 'target',
                'conditional_logic' => [
                    'visibility' => [
                        'enabled' => true,
                        'match' => 'all',
                        'rules' => [
                            ['source_field_key' => 'source', 'operator' => $op, 'value' => $expected],
                        ],
                    ],
                ],
            ];

            $this->assertSame(
                $shouldBeVisible,
                $this->evaluator->evaluateVisibility($field, ['source' => $actual]),
                "Failed operator [{$op}] visibility check."
            );
        }
    }

    public function test_hidden_parent_cascades_and_sanitizes_wild_answers(): void
    {
        $schema = [
            'sections' => [
                [
                    'section_key' => 'sec_1',
                    'sort_order' => 1,
                    'next_section_rule' => ['action' => 'next', 'target_section_key' => null],
                    'fields' => [
                        [
                            'field_key' => 'jenis_layanan',
                            'type' => 'radio',
                            'sort_order' => 1,
                            'options' => [
                                'choices' => [
                                    ['key' => 'rawat_inap', 'label' => 'Rawat Inap'],
                                    ['key' => 'rawat_jalan', 'label' => 'Rawat Jalan'],
                                ],
                            ],
                        ],
                        [
                            'field_key' => 'kelas_kamar',
                            'type' => 'short_text',
                            'sort_order' => 2,
                            'conditional_logic' => [
                                'visibility' => [
                                    'enabled' => true,
                                    'match' => 'all',
                                    'rules' => [
                                        ['source_field_key' => 'jenis_layanan', 'operator' => 'equals', 'value' => 'rawat_inap'],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'field_key' => 'info_banner',
                            'type' => 'info_block',
                            'sort_order' => 3,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->evaluator->evaluate($schema, [
            'jenis_layanan' => ['selected' => 'rawat_jalan'],
            'kelas_kamar' => 'VIP (nilai liar dari klien)',
            'info_banner' => 'nilai liar non-input',
            'unknown_field' => 'tidak terdaftar',
        ]);

        $this->assertSame(['sec_1'], $result['active_section_keys']);
        $this->assertSame(['jenis_layanan', 'info_banner'], $result['visible_field_keys']);
        $this->assertSame(['jenis_layanan' => ['selected' => 'rawat_jalan']], $result['sanitized_answers']);
    }

    public function test_section_jump_skips_inactive_sections_and_strips_their_answers(): void
    {
        $schema = [
            'sections' => [
                [
                    'section_key' => 'sec_1',
                    'sort_order' => 1,
                    'next_section_rule' => ['action' => 'next', 'target_section_key' => null],
                    'fields' => [
                        [
                            'field_key' => 'unit',
                            'type' => 'dropdown',
                            'sort_order' => 1,
                            'options' => [
                                'choices' => [
                                    ['key' => 'igd', 'label' => 'IGD', 'go_to_section_key' => 'sec_3'],
                                    ['key' => 'poli', 'label' => 'Poliklinik', 'go_to_section_key' => null],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'section_key' => 'sec_2',
                    'sort_order' => 2,
                    'next_section_rule' => ['action' => 'submit', 'target_section_key' => '__submit__'],
                    'fields' => [
                        ['field_key' => 'poli_note', 'type' => 'short_text', 'sort_order' => 1],
                    ],
                ],
                [
                    'section_key' => 'sec_3',
                    'sort_order' => 3,
                    'next_section_rule' => ['action' => 'next', 'target_section_key' => null],
                    'fields' => [
                        ['field_key' => 'igd_speed', 'type' => 'rating', 'sort_order' => 1],
                    ],
                ],
            ],
        ];

        $result = $this->evaluator->evaluate($schema, [
            'unit' => 'igd',
            'poli_note' => 'harus terbuang karena sec_2 dilewati',
            'igd_speed' => 5,
        ]);

        $this->assertSame(['sec_1', 'sec_3'], $result['active_section_keys']);
        $this->assertSame(['unit' => 'igd', 'igd_speed' => 5], $result['sanitized_answers']);
    }

    public function test_validate_schema_logic_rejects_cycles_backward_jumps_and_future_dependencies(): void
    {
        $sectionsWithIssues = [
            [
                'section_key' => 'sec_1',
                'title' => 'Bagian 1',
                'next_section_rule' => ['action' => 'next', 'target_section_key' => null],
                'fields' => [
                    [
                        'field_key' => 'q1',
                        'label' => 'Pertanyaan 1',
                        'type' => 'short_text',
                        'conditional_logic' => [
                            'visibility' => [
                                'enabled' => true,
                                'match' => 'all',
                                'rules' => [
                                    ['source_field_key' => 'q2', 'operator' => 'equals', 'value' => 'ya'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'field_key' => 'q2',
                        'label' => 'Pertanyaan 2',
                        'type' => 'radio',
                        'options' => [
                            'choices' => [
                                ['key' => 'ya', 'label' => 'Ya', 'go_to_section_key' => 'sec_2'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'section_key' => 'sec_2',
                'title' => 'Bagian 2',
                'next_section_rule' => ['action' => 'jump', 'target_section_key' => 'sec_1'],
                'fields' => [
                    ['field_key' => 'q3', 'label' => 'Pertanyaan 3', 'type' => 'short_text'],
                ],
            ],
        ];

        $errors = $this->evaluator->validateSchemaLogic($sectionsWithIssues);

        $this->assertNotEmpty($errors);
        $joined = implode(' ', $errors);
        $this->assertStringContainsString('muncul sebelumnya', $joined);
        $this->assertStringContainsString('mundur', $joined);
        $this->assertStringContainsString('siklus', $joined);
    }

    public function test_validate_schema_logic_rejects_info_block_as_visibility_source(): void
    {
        $sections = [
            [
                'section_key' => 'sec_1',
                'title' => 'Bagian 1',
                'fields' => [
                    [
                        'field_key' => 'info_awal',
                        'label' => 'Informasi Awal',
                        'type' => 'info_block',
                    ],
                    [
                        'field_key' => 'tindak_lanjut',
                        'label' => 'Tindak Lanjut',
                        'type' => 'short_text',
                        'conditional_logic' => [
                            'visibility' => [
                                'enabled' => true,
                                'match' => 'all',
                                'rules' => [
                                    ['source_field_key' => 'info_awal', 'operator' => 'is_answered'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $errors = $this->evaluator->validateSchemaLogic($sections);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Blok Informasi', implode(' ', $errors));
    }

    public function test_validate_unique_raw_keys_rejects_duplicate_choice_row_and_column_keys_within_same_field(): void
    {
        $sections = [
            [
                'section_key' => 'sec_1',
                'title' => 'Bagian 1',
                'fields' => [
                    [
                        'field_key' => 'pilihan_1',
                        'type' => 'radio',
                        'label' => 'Pilihan Pertama',
                        'options' => [
                            'choices' => [
                                ['key' => 'ya', 'label' => 'Ya'],
                                ['key' => 'YA', 'label' => 'Ya Juga'],
                            ],
                        ],
                    ],
                    [
                        'field_key' => 'pilihan_2',
                        'type' => 'dropdown',
                        'label' => 'Pilihan Kedua',
                        'options' => [
                            'choices' => [
                                ['key' => 'ya', 'label' => 'Ya'],
                                ['key' => 'tidak', 'label' => 'Tidak'],
                            ],
                        ],
                    ],
                    [
                        'field_key' => 'matriks',
                        'type' => 'matrix_grid',
                        'label' => 'Kisi Mutu',
                        'options' => [
                            'rows' => [
                                ['key' => 'sama', 'label' => 'Baris 1'],
                                ['key' => 'sama', 'label' => 'Baris 2'],
                            ],
                            'columns' => [
                                ['key' => 'sama', 'label' => 'Kolom 1'],
                                ['key' => 'kol_dup', 'label' => 'Kolom 2'],
                                ['key' => 'kol_dup', 'label' => 'Kolom 3'],
                            ],
                        ],
                    ],
                    [
                        // Tipe teks singkat dengan sisa state tersembunyi choices tidak boleh memicu false positive
                        'field_key' => 'catatan',
                        'type' => 'short_text',
                        'label' => 'Catatan',
                        'options' => [
                            'choices' => [
                                ['key' => 'stale', 'label' => 'Stale 1'],
                                ['key' => 'stale', 'label' => 'Stale 2'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $errors = $this->evaluator->validateUniqueRawKeys($sections);
        $joined = implode(' ', $errors);

        $this->assertCount(3, $errors);
        $this->assertStringContainsString('Kunci pilihan [ya] pada pertanyaan [Pilihan Pertama] terduplikasi', $joined);
        $this->assertStringContainsString('Kunci baris matrix [sama] pada pertanyaan [Kisi Mutu] terduplikasi', $joined);
        $this->assertStringContainsString('Kunci kolom matrix [kol_dup] pada pertanyaan [Kisi Mutu] terduplikasi', $joined);
        $this->assertStringNotContainsString('Pilihan Kedua', $joined);
        $this->assertStringNotContainsString('stale', $joined);

        // Pastikan enum DynamicFormFieldType sebagai type tidak memicu error casting string
        $enumTypedErrors = $this->evaluator->validateUniqueRawKeys([
            [
                'section_key' => 'sec_enum',
                'fields' => [
                    [
                        'field_key' => 'f_enum',
                        'type' => DynamicFormFieldType::Radio,
                        'label' => 'Enum Radio',
                        'options' => [
                            'choices' => [
                                ['key' => 'sama', 'label' => 'A'],
                                ['key' => 'sama', 'label' => 'B'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->assertCount(1, $enumTypedErrors);
        $this->assertStringContainsString('Enum Radio', $enumTypedErrors[0]);
    }
}
