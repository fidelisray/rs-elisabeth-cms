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

    public function test_allowed_operators_and_options_match_each_field_type(): void
    {
        $this->assertSame(
            ['equals', 'not_equals', 'in', 'not_in', 'is_empty', 'is_not_empty'],
            DynamicFormLogicEvaluator::allowedOperatorsForField(['type' => 'radio'])
        );
        $this->assertSame(
            ['equals', 'not_equals', 'in', 'not_in', 'is_empty', 'is_not_empty'],
            DynamicFormLogicEvaluator::allowedOperatorsForField(['type' => 'dropdown'])
        );
        $this->assertSame(
            ['contains', 'in', 'not_in', 'equals', 'not_equals', 'is_empty', 'is_not_empty'],
            DynamicFormLogicEvaluator::allowedOperatorsForField(['type' => 'checkbox'])
        );
        $this->assertSame(
            ['equals', 'not_equals', 'gt', 'gte', 'lt', 'lte', 'in', 'not_in', 'is_empty', 'is_not_empty'],
            DynamicFormLogicEvaluator::allowedOperatorsForField(['type' => 'linear_scale'])
        );
        $this->assertSame(
            ['equals', 'not_equals', 'gt', 'gte', 'lt', 'lte', 'in', 'not_in', 'is_empty', 'is_not_empty'],
            DynamicFormLogicEvaluator::allowedOperatorsForField(['type' => 'rating'])
        );
        $this->assertSame(
            ['equals', 'not_equals', 'gt', 'gte', 'lt', 'lte', 'in', 'not_in', 'is_empty', 'is_not_empty'],
            DynamicFormLogicEvaluator::allowedOperatorsForField(['type' => 'short_text', 'options' => ['subtype' => 'number']])
        );
        $this->assertSame(
            ['equals', 'not_equals', 'contains', 'in', 'not_in', 'is_empty', 'is_not_empty'],
            DynamicFormLogicEvaluator::allowedOperatorsForField(['type' => 'short_text', 'options' => ['subtype' => 'text']])
        );
        $this->assertSame(
            ['equals', 'not_equals', 'contains', 'in', 'not_in', 'is_empty', 'is_not_empty'],
            DynamicFormLogicEvaluator::allowedOperatorsForField(['type' => 'long_text'])
        );
        $this->assertSame(
            ['equals', 'not_equals', 'is_empty', 'is_not_empty'],
            DynamicFormLogicEvaluator::allowedOperatorsForField(['type' => 'date'])
        );
        $this->assertSame(
            ['equals', 'not_equals', 'is_empty', 'is_not_empty'],
            DynamicFormLogicEvaluator::allowedOperatorsForField(['type' => 'time'])
        );
        $this->assertSame(
            ['is_empty', 'is_not_empty'],
            DynamicFormLogicEvaluator::allowedOperatorsForField(['type' => 'file_upload'])
        );
        $this->assertSame(
            ['is_empty', 'is_not_empty'],
            DynamicFormLogicEvaluator::allowedOperatorsForField(['type' => 'matrix_grid'])
        );
        $this->assertSame([], DynamicFormLogicEvaluator::allowedOperatorsForField(['type' => 'info_block']));

        $radioOps = DynamicFormLogicEvaluator::operatorOptionsForField(['type' => 'radio']);
        $this->assertSame('Salah satu dari', $radioOps['in']);
        $this->assertArrayNotHasKey('gt', $radioOps);
        $this->assertArrayNotHasKey('contains', $radioOps);
    }

    public function test_validate_schema_logic_rejects_empty_values_invalid_choice_keys_and_incompatible_operators(): void
    {
        $sections = [
            [
                'section_key' => 'sec_1',
                'title' => 'Bagian 1',
                'fields' => [
                    [
                        'field_key' => 'kepatuhan',
                        'label' => 'Kepatuhan Browser',
                        'type' => 'radio',
                        'options' => [
                            'choices' => [
                                ['key' => 'patuh', 'label' => 'Patuh'],
                                ['key' => 'tidak_patuh', 'label' => 'Tidak Patuh'],
                            ],
                            'allow_other' => false,
                        ],
                    ],
                    [
                        'field_key' => 'hobi',
                        'label' => 'Hobi',
                        'type' => 'checkbox',
                        'options' => [
                            'choices' => [
                                ['key' => 'baca', 'label' => 'Membaca'],
                            ],
                            'allow_other' => true,
                        ],
                    ],
                    [
                        'field_key' => 'lampiran',
                        'label' => 'Lampiran',
                        'type' => 'file_upload',
                    ],
                    [
                        'field_key' => 'matriks',
                        'label' => 'Matriks',
                        'type' => 'matrix_grid',
                        'options' => [
                            'rows' => [['key' => 'r1', 'label' => 'R1']],
                            'columns' => [['key' => 'c1', 'label' => 'C1']],
                        ],
                    ],
                    [
                        'field_key' => 't_bad_choice',
                        'label' => 'Target Opsi Salah',
                        'type' => 'short_text',
                        'conditional_logic' => [
                            'visibility' => [
                                'enabled' => true,
                                'match' => 'all',
                                'rules' => [
                                    ['source_field_key' => 'kepatuhan', 'operator' => 'equals', 'value' => 'Tidak Patuh'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'field_key' => 't_other_disallowed',
                        'label' => 'Target Other Tidak Aktif',
                        'type' => 'short_text',
                        'conditional_logic' => [
                            'visibility' => [
                                'enabled' => true,
                                'match' => 'all',
                                'rules' => [
                                    ['source_field_key' => 'kepatuhan', 'operator' => 'equals', 'value' => '__other__'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'field_key' => 't_other_allowed',
                        'label' => 'Target Other Aktif',
                        'type' => 'short_text',
                        'conditional_logic' => [
                            'visibility' => [
                                'enabled' => true,
                                'match' => 'all',
                                'rules' => [
                                    ['source_field_key' => 'hobi', 'operator' => 'contains', 'value' => '__other__'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'field_key' => 't_empty_value',
                        'label' => 'Target Nilai Kosong',
                        'type' => 'short_text',
                        'conditional_logic' => [
                            'visibility' => [
                                'enabled' => true,
                                'match' => 'all',
                                'rules' => [
                                    ['source_field_key' => 'kepatuhan', 'operator' => 'in', 'value' => ['   ']],
                                ],
                            ],
                        ],
                    ],
                    [
                        'field_key' => 't_bad_op_file',
                        'label' => 'Target Operator File',
                        'type' => 'short_text',
                        'conditional_logic' => [
                            'visibility' => [
                                'enabled' => true,
                                'match' => 'all',
                                'rules' => [
                                    ['source_field_key' => 'lampiran', 'operator' => 'gt', 'value' => '1'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'field_key' => 't_bad_op_matrix',
                        'label' => 'Target Operator Matrix',
                        'type' => 'short_text',
                        'conditional_logic' => [
                            'visibility' => [
                                'enabled' => true,
                                'match' => 'all',
                                'rules' => [
                                    ['source_field_key' => 'matriks', 'operator' => 'lt', 'value' => '2'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $errors = $this->evaluator->validateSchemaLogic($sections);
        $joined = implode(' | ', $errors);

        $this->assertStringContainsString('Nilai pembanding [Tidak Patuh]', $joined);
        $this->assertStringContainsString('Nilai pembanding [__other__]', $joined);
        $this->assertStringNotContainsString('Target Other Aktif', $joined);
        $this->assertStringContainsString('Target Nilai Kosong', $joined);
        $this->assertStringContainsString('Operator [gt]', $joined);
        $this->assertStringContainsString('Operator [lt]', $joined);
    }

    public function test_validate_raw_logic_config_rejects_duplicate_visibility_choice_jump_and_section_jump_rules(): void
    {
        $errors = $this->evaluator->validateRawLogicConfig([
            'visibility_rules' => [
                ['target_field_key' => 'q2', 'match' => 'all', 'rules' => []],
                ['target_field_key' => 'q2', 'match' => 'any', 'rules' => []],
            ],
            'choice_jumps' => [
                ['source_field_key' => 'q1', 'choice_key' => 'ya', 'go_to_section_key' => 'sec_2'],
                ['source_field_key' => 'q1', 'choice_key' => 'ya', 'go_to_section_key' => 'sec_3'],
            ],
            'section_jumps' => [
                ['section_key' => 'sec_1', 'action' => 'jump', 'target_section_key' => 'sec_2'],
                ['section_key' => 'sec_1', 'action' => 'submit', 'target_section_key' => null],
            ],
        ]);

        $this->assertCount(3, $errors);
        $joined = implode(' ', $errors);
        $this->assertStringContainsString('aturan visibilitas untuk pertanyaan target [q2]', $joined);
        $this->assertStringContainsString('lompatan pilihan untuk pertanyaan [q1] pada opsi [ya]', $joined);
        $this->assertStringContainsString('aturan akhir bagian untuk bagian [sec_1]', $joined);
    }

    public function test_parity_edge_cases_for_whitespace_only_strings_and_matrix_grid_maps(): void
    {
        $cases = [
            ['is_empty', null, '   ', true],
            ['is_empty', null, "\t\n ", true],
            ['is_empty', null, ['   ', ''], true],
            ['is_not_empty', null, '   ', false],
            ['equals', '   ', '   ', false],
            ['lt', '3', '   ', false],
            ['gte', '0', '   ', false],
            ['is_empty', null, 0, false],
            ['is_empty', null, '0', false],
            ['is_empty', null, ['r1' => '', 'r2' => '   '], true],
            ['is_empty', null, ['r1' => ['   ']], true],
            ['is_not_empty', null, ['r1' => 'c1'], true],
            ['is_not_empty', null, ['r1' => ['c1']], true],
            ['equals', '[object Object]', ['r1' => 'c1'], false],
            ['equals', 'c1', ['r1' => 'c1'], false],
            ['not_equals', 'c1', ['r1' => 'c1'], false],
            ['in', ['[object Object]', 'c1'], ['r1' => 'c1'], false],
            ['not_in', ['c1'], ['r1' => 'c1'], false],
            ['contains', 'c1', ['r1' => ['c1']], false],
            ['gt', '0', ['r1' => 'c1'], false],
        ];

        foreach ($cases as [$op, $expected, $actual, $shouldBeVisible]) {
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
                "Failed parity check for operator [{$op}]."
            );
        }
    }

    public function test_validate_schema_logic_rejects_array_on_scalar_operator_and_non_numeric_on_range_operators(): void
    {
        $sections = [[
            'section_key' => 'sec_1',
            'title' => 'Bagian 1',
            'fields' => [
                [
                    'field_key' => 'pilihan',
                    'label' => 'Pilihan',
                    'type' => 'radio',
                    'options' => [
                        'choices' => [
                            ['key' => 'ya', 'label' => 'Ya'],
                            ['key' => 'tidak', 'label' => 'Tidak'],
                        ],
                    ],
                ],
                [
                    'field_key' => 'skala',
                    'label' => 'Skala',
                    'type' => 'linear_scale',
                    'options' => ['min_value' => 1, 'max_value' => 5],
                ],
                [
                    'field_key' => 't_array_equals',
                    'label' => 'Target Array Equals',
                    'type' => 'short_text',
                    'conditional_logic' => [
                        'visibility' => [
                            'enabled' => true,
                            'match' => 'all',
                            'rules' => [
                                ['source_field_key' => 'pilihan', 'operator' => 'equals', 'value' => ['ya', 'tidak']],
                            ],
                        ],
                    ],
                ],
                [
                    'field_key' => 't_non_numeric_gt',
                    'label' => 'Target Non Numeric GT',
                    'type' => 'short_text',
                    'conditional_logic' => [
                        'visibility' => [
                            'enabled' => true,
                            'match' => 'all',
                            'rules' => [
                                ['source_field_key' => 'skala', 'operator' => 'gt', 'value' => 'bukan_angka'],
                            ],
                        ],
                    ],
                ],
            ],
        ]];

        $errors = $this->evaluator->validateSchemaLogic($sections);
        $joined = implode(' | ', $errors);

        $this->assertStringContainsString('harus berupa satu nilai tunggal untuk operator [equals]', $joined);
        $this->assertStringContainsString('harus berupa angka untuk operator [gt]', $joined);
    }

    public function test_validate_schema_logic_rejects_multiple_jump_source_fields_in_same_section(): void
    {
        // 1. Konflik: 2 pertanyaan berbeda di Bagian 1 sama-sama punya lompatan (termasuk __submit__) -> ditolak
        $conflictingSections = [
            [
                'section_key' => 'sec_1',
                'title' => 'Identitas & Layanan',
                'fields' => [
                    [
                        'field_key' => 'unit_layanan',
                        'label' => 'Unit Layanan',
                        'type' => 'radio',
                        'options' => [
                            'choices' => [
                                ['key' => 'igd', 'label' => 'IGD', 'go_to_section_key' => 'sec_2'],
                                ['key' => 'rajal', 'label' => 'Rawat Jalan', 'go_to_section_key' => 'sec_3'],
                            ],
                        ],
                    ],
                    [
                        'field_key' => 'bersedia_lanjut',
                        'label' => 'Bersedia Melanjutkan?',
                        'type' => 'dropdown',
                        'options' => [
                            'choices' => [
                                ['key' => 'tidak', 'label' => 'Tidak', 'go_to_section_key' => '__submit__'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'section_key' => 'sec_2',
                'title' => 'Evaluasi IGD',
                'fields' => [
                    ['field_key' => 'catatan_igd', 'label' => 'Catatan IGD', 'type' => 'short_text'],
                ],
            ],
            [
                'section_key' => 'sec_3',
                'title' => 'Evaluasi Rawat Jalan',
                'fields' => [
                    ['field_key' => 'catatan_rajal', 'label' => 'Catatan Rawat Jalan', 'type' => 'short_text'],
                ],
            ],
        ];

        $conflictErrors = $this->evaluator->validateSchemaLogic($conflictingSections);
        $this->assertNotEmpty($conflictErrors);
        $this->assertStringContainsString(
            'memiliki lebih dari satu pertanyaan pemicu Lompatan Bagian (Unit Layanan, Bersedia Melanjutkan?)',
            implode(' | ', $conflictErrors)
        );

        // 2. Valid: 1 pertanyaan pemicu di Bagian 1 (beberapa opsi lompatan) + 1 pertanyaan pemicu di Bagian 2 -> diterima
        $validSections = [
            [
                'section_key' => 'sec_1',
                'title' => 'Identitas & Layanan',
                'fields' => [
                    [
                        'field_key' => 'unit_layanan',
                        'label' => 'Unit Layanan',
                        'type' => 'radio',
                        'options' => [
                            'choices' => [
                                ['key' => 'igd', 'label' => 'IGD', 'go_to_section_key' => 'sec_2'],
                                ['key' => 'rajal', 'label' => 'Rawat Jalan', 'go_to_section_key' => 'sec_3'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'section_key' => 'sec_2',
                'title' => 'Evaluasi IGD',
                'fields' => [
                    [
                        'field_key' => 'perlu_penutup',
                        'label' => 'Ingin Tambah Saran?',
                        'type' => 'radio',
                        'options' => [
                            'choices' => [
                                ['key' => 'tidak', 'label' => 'Tidak', 'go_to_section_key' => '__submit__'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'section_key' => 'sec_3',
                'title' => 'Evaluasi Rawat Jalan',
                'fields' => [
                    ['field_key' => 'catatan_rajal', 'label' => 'Catatan Rawat Jalan', 'type' => 'short_text'],
                ],
            ],
        ];

        $this->assertSame([], $this->evaluator->validateSchemaLogic($validSections));
    }
}
