<?php

namespace Tests\Unit;

use App\Models\DynamicForm;
use App\Models\DynamicFormUpload;
use App\Services\DynamicForm\DynamicFormHtmlSanitizer;
use App\Services\DynamicForm\DynamicFormSubmissionValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DynamicFormSubmissionValidatorTest extends TestCase
{
    use RefreshDatabase;

    private DynamicFormSubmissionValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = app(DynamicFormSubmissionValidator::class);
    }

    public function test_validates_all_twelve_field_types_and_strips_hidden_and_info_block(): void
    {
        Storage::fake('private-forms');
        Storage::disk('private-forms')->put('dynamic-forms/1/sample.pdf', '%PDF-1.4 sample');

        $form = DynamicForm::factory()->create();
        $upload = DynamicFormUpload::create([
            'token' => (string) Str::uuid(),
            'dynamic_form_id' => $form->id,
            'field_key' => 'dokumen',
            'disk' => 'private-forms',
            'storage_path' => 'dynamic-forms/1/sample.pdf',
            'original_name' => 'surat.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 15,
            'expires_at' => now()->addHour(),
        ]);

        $schema = [
            'sections' => [
                [
                    'section_key' => 'sec_1',
                    'sort_order' => 1,
                    'next_section_rule' => ['action' => 'next', 'target_section_key' => null],
                    'fields' => [
                        ['field_key' => 'intro', 'type' => 'info_block', 'label' => 'Petunjuk', 'sort_order' => 1],
                        ['field_key' => 'email', 'type' => 'short_text', 'label' => 'Email', 'is_required' => true, 'sort_order' => 2, 'options' => ['subtype' => 'email']],
                        ['field_key' => 'uraian', 'type' => 'long_text', 'label' => 'Uraian', 'is_required' => true, 'sort_order' => 3, 'validation_rules' => ['min_length' => 5]],
                        [
                            'field_key' => 'kanal',
                            'type' => 'radio',
                            'label' => 'Kanal',
                            'is_required' => true,
                            'sort_order' => 4,
                            'options' => [
                                'choices' => [['key' => 'web', 'label' => 'Web']],
                                'allow_other' => true,
                            ],
                        ],
                        [
                            'field_key' => 'detail_web',
                            'type' => 'short_text',
                            'label' => 'Detail Web',
                            'is_required' => true,
                            'sort_order' => 5,
                            'conditional_logic' => [
                                'visibility' => [
                                    'enabled' => true,
                                    'match' => 'all',
                                    'rules' => [['source_field_key' => 'kanal', 'operator' => 'equals', 'value' => 'web']],
                                ],
                            ],
                        ],
                        [
                            'field_key' => 'topik',
                            'type' => 'checkbox',
                            'label' => 'Topik',
                            'is_required' => true,
                            'sort_order' => 6,
                            'options' => [
                                'choices' => [['key' => 'parkir', 'label' => 'Parkir'], ['key' => 'antri', 'label' => 'Antrean']],
                                'allow_other' => true,
                            ],
                            'validation_rules' => ['min_selected' => 1, 'max_selected' => 2],
                        ],
                        [
                            'field_key' => 'unit',
                            'type' => 'dropdown',
                            'label' => 'Unit',
                            'is_required' => true,
                            'sort_order' => 7,
                            'options' => ['choices' => [['key' => 'igd', 'label' => 'IGD']]],
                        ],
                        ['field_key' => 'skala', 'type' => 'linear_scale', 'label' => 'Skala', 'is_required' => true, 'sort_order' => 8, 'options' => ['min_value' => 1, 'max_value' => 5]],
                        ['field_key' => 'bintang', 'type' => 'rating', 'label' => 'Bintang', 'is_required' => true, 'sort_order' => 9, 'options' => ['max_stars' => 5]],
                        [
                            'field_key' => 'matrix',
                            'type' => 'matrix_grid',
                            'label' => 'Penilaian Unit',
                            'is_required' => true,
                            'sort_order' => 10,
                            'options' => [
                                'selection_mode' => 'single',
                                'require_all_rows' => true,
                                'rows' => [['key' => 'ramah', 'label' => 'Keramahan']],
                                'columns' => [['key' => 'puas', 'label' => 'Puas', 'score' => 4]],
                            ],
                        ],
                        ['field_key' => 'tgl', 'type' => 'date', 'label' => 'Tanggal', 'is_required' => true, 'sort_order' => 11],
                        ['field_key' => 'jam', 'type' => 'time', 'label' => 'Jam', 'is_required' => true, 'sort_order' => 12],
                        ['field_key' => 'dokumen', 'type' => 'file_upload', 'label' => 'Dokumen', 'is_required' => true, 'sort_order' => 13, 'options' => ['max_files' => 2]],
                    ],
                ],
            ],
        ];

        // kanal memilih '__other__' sehingga 'detail_web' (yang is_required=true) tersembunyi dan lolos validasi!
        $validated = $this->validator->validate($form, $schema, [
            'intro' => 'should be stripped',
            'email' => 'pasien@example.com',
            'uraian' => 'Pelayanan cepat dan jelas.',
            'kanal' => ['selected' => '__other__', 'other_text' => 'Aplikasi Mobile'],
            'detail_web' => 'nilai tersembunyi harus dibuang',
            'topik' => ['selected' => ['parkir', '__other__'], 'other_text' => 'AC Ruang Tunggu'],
            'unit' => 'igd',
            'skala' => 4,
            'bintang' => 5,
            'matrix' => ['ramah' => 'puas'],
            'tgl' => '2026-11-18',
            'jam' => '09:30',
            'dokumen' => [$upload->token],
        ]);

        $this->assertArrayNotHasKey('intro', $validated['answers']);
        $this->assertArrayNotHasKey('detail_web', $validated['answers']);
        $this->assertSame([$upload->id], $validated['upload_ids']);
        $this->assertSame('Aplikasi Mobile', $validated['answers']['kanal']['other_text']);
        $this->assertSame(['ramah' => 'puas'], $validated['answers']['matrix']);
    }

    public function test_rejects_expired_cross_form_and_already_attached_upload_tokens(): void
    {
        Storage::fake('private-forms');
        Storage::disk('private-forms')->put('dynamic-forms/1/a.pdf', '%PDF-1.4');

        $form = DynamicForm::factory()->create();
        $otherForm = DynamicForm::factory()->create();

        $expired = DynamicFormUpload::create([
            'token' => (string) Str::uuid(),
            'dynamic_form_id' => $form->id,
            'field_key' => 'lampiran',
            'disk' => 'private-forms',
            'storage_path' => 'dynamic-forms/1/a.pdf',
            'original_name' => 'a.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 8,
            'expires_at' => now()->subMinute(),
        ]);

        $crossForm = DynamicFormUpload::create([
            'token' => (string) Str::uuid(),
            'dynamic_form_id' => $otherForm->id,
            'field_key' => 'lampiran',
            'disk' => 'private-forms',
            'storage_path' => 'dynamic-forms/1/a.pdf',
            'original_name' => 'b.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 8,
            'expires_at' => now()->addHour(),
        ]);

        $schema = [
            'sections' => [[
                'section_key' => 's1',
                'sort_order' => 1,
                'fields' => [[
                    'field_key' => 'lampiran',
                    'type' => 'file_upload',
                    'label' => 'Lampiran',
                    'is_required' => true,
                    'sort_order' => 1,
                    'options' => ['max_files' => 2],
                ]],
            ]],
        ];

        foreach ([$expired->token, $crossForm->token] as $badToken) {
            try {
                $this->validator->validate($form, $schema, ['lampiran' => [$badToken]]);
                $this->fail('Token tidak valid seharusnya ditolak.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('answers.lampiran', $e->errors());
            }
        }
    }

    public function test_validates_text_format_presets_indonesian_phone_numbers_and_legacy_pattern_fallback(): void
    {
        $form = DynamicForm::factory()->create();

        $schema = [
            'sections' => [[
                'section_key' => 's1',
                'sort_order' => 1,
                'fields' => [
                    [
                        'field_key' => 'nama',
                        'type' => 'short_text',
                        'label' => 'Nama Lengkap',
                        'is_required' => true,
                        'sort_order' => 1,
                        'options' => ['subtype' => 'text'],
                        'validation_rules' => ['format_preset' => 'letters_spaces'],
                    ],
                    [
                        'field_key' => 'nik',
                        'type' => 'short_text',
                        'label' => 'NIK',
                        'is_required' => true,
                        'sort_order' => 2,
                        'options' => ['subtype' => 'text'],
                        'validation_rules' => ['format_preset' => 'digits_only', 'min_length' => 16, 'max_length' => 16],
                    ],
                    [
                        'field_key' => 'kode_rm',
                        'type' => 'short_text',
                        'label' => 'Kode RM',
                        'is_required' => true,
                        'sort_order' => 3,
                        'options' => ['subtype' => 'text'],
                        'validation_rules' => ['format_preset' => 'code_dash'],
                    ],
                    [
                        'field_key' => 'hp',
                        'type' => 'short_text',
                        'label' => 'Nomor HP',
                        'is_required' => true,
                        'sort_order' => 4,
                        'options' => ['subtype' => 'phone'],
                    ],
                    [
                        'field_key' => 'legacy_kode',
                        'type' => 'short_text',
                        'label' => 'Kode Lama',
                        'is_required' => false,
                        'sort_order' => 5,
                        'options' => ['subtype' => 'text'],
                        'validation_rules' => ['pattern' => '^REG-[0-9]{4}$'],
                    ],
                ],
            ]],
        ];

        $validated = $this->validator->validate($form, $schema, [
            'nama' => "dr. Budi Santoso, Sp.PD-KGEH Ma'ruf",
            'nik' => '0033740101900001',
            'kode_rm' => 'RM-001234',
            'hp' => '+62 812-3456-7890',
            'legacy_kode' => 'REG-2026',
        ]);

        $this->assertSame('0033740101900001', $validated['answers']['nik']);
        $this->assertSame('+62 812-3456-7890', $validated['answers']['hp']);

        // Uji seluruh varian valid & tidak valid untuk nomor telepon Indonesia (10-13 digit lokal)
        foreach (['0812345678', '0812345678901', '+6281234567890', '6281234567890', '0812-3456-7890', '0812.3456.7890'] as $validPhone) {
            $this->assertTrue(DynamicFormSubmissionValidator::isValidIndonesianPhone($validPhone), "Expected valid phone: {$validPhone}");
        }
        foreach (['081234567', '08123456789012', '08012345678', '0248310076', '1234567890', '0812abcd7890', ''] as $invalidPhone) {
            $this->assertFalse(DynamicFormSubmissionValidator::isValidIndonesianPhone($invalidPhone), "Expected invalid phone: {$invalidPhone}");
        }

        // Uji penolakan karakter ilegal + pesan error spesifik
        try {
            $this->validator->validate($form, $schema, [
                'nama' => 'Budi 123',
                'nik' => '3374ABC101900001',
                'kode_rm' => 'RM 001!',
                'hp' => '081234567',
                'legacy_kode' => 'SALAH',
            ]);
            $this->fail('Format tidak valid seharusnya ditolak.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertStringContainsString('huruf, spasi, dan tanda baca nama umum', $errors['answers.nama'][0]);
            $this->assertStringContainsString('hanya boleh berisi angka', $errors['answers.nik'][0]);
            $this->assertStringContainsString('huruf, angka, dan tanda hubung', $errors['answers.kode_rm'][0]);
            $this->assertStringContainsString('nomor telepon Indonesia', $errors['answers.hp'][0]);
            $this->assertStringContainsString('tidak sesuai pola yang diminta', $errors['answers.legacy_kode'][0]);
        }
    }

    public function test_html_sanitizer_preserves_safe_formatting_strips_xss_and_normalizes_empty_markup(): void
    {
        $sanitizer = app(DynamicFormHtmlSanitizer::class);

        $this->assertNull($sanitizer->sanitize(null));
        $this->assertNull($sanitizer->sanitize('   '));
        $this->assertNull($sanitizer->sanitize('<p></p>'));
        $this->assertNull($sanitizer->sanitize('<p><br>&nbsp;</p>'));

        $dirty = '<h2>Petunjuk</h2><p>Isi <strong>dengan jujur</strong></p><ul><li>Item 1</li></ul>'
            .'<a href="https://rs-elisabeth.com/info" target="_blank">Panduan</a> '
            .'<a href="javascript:alert(1)">XSS</a>'
            .'<script>alert(1)</script><img src="x" onerror="alert(2)">';

        $clean = (string) $sanitizer->sanitize($dirty);
        $this->assertStringContainsString('<h2>Petunjuk</h2>', $clean);
        $this->assertStringContainsString('<strong>dengan jujur</strong>', $clean);
        $this->assertStringContainsString('<ul><li>Item 1</li></ul>', $clean);
        $this->assertStringContainsString('href="https://rs-elisabeth.com/info"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('<img', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('onerror', $clean);

        $this->assertSame('Petunjuk Isi dengan jujur Item 1 Panduan XSS', $sanitizer->toPlainText($clean));

        // Uji array dokumen TipTap mentah dari state Livewire RichEditor (kosong, berformat, dan array tidak valid)
        $emptyTipTapDoc = [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => []],
            ],
        ];
        $this->assertNull($sanitizer->sanitize($emptyTipTapDoc));
        $this->assertNull($sanitizer->sanitize(['invalid' => 'payload']));

        $richTipTapDoc = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'heading',
                    'attrs' => ['level' => 2],
                    'content' => [['type' => 'text', 'text' => 'Aturan Pengisian']],
                ],
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Harap isi '],
                        ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'dengan teliti'],
                        ['type' => 'text', 'text' => ' dan baca '],
                        [
                            'type' => 'text',
                            'marks' => [[
                                'type' => 'link',
                                'attrs' => ['href' => 'https://rs-elisabeth.com/panduan', 'target' => '_blank'],
                            ]],
                            'text' => 'Panduan',
                        ],
                        ['type' => 'text', 'text' => ' '],
                        [
                            'type' => 'text',
                            'marks' => [[
                                'type' => 'link',
                                'attrs' => ['href' => 'javascript:alert(1)'],
                            ]],
                            'text' => 'Bahaya',
                        ],
                    ],
                ],
                [
                    'type' => 'bulletList',
                    'content' => [[
                        'type' => 'listItem',
                        'content' => [[
                            'type' => 'paragraph',
                            'content' => [['type' => 'text', 'text' => 'Siapkan nomor RM']],
                        ]],
                    ]],
                ],
            ],
        ];

        $cleanFromTipTap = (string) $sanitizer->sanitize($richTipTapDoc);
        $this->assertStringContainsString('<h2>Aturan Pengisian</h2>', $cleanFromTipTap);
        $this->assertStringContainsString('<strong>dengan teliti</strong>', $cleanFromTipTap);
        $this->assertStringContainsString('href="https://rs-elisabeth.com/panduan"', $cleanFromTipTap);
        $this->assertStringContainsString('rel="noopener noreferrer"', $cleanFromTipTap);
        $this->assertStringContainsString('Siapkan nomor RM', $cleanFromTipTap);
        $this->assertStringNotContainsString('javascript:', $cleanFromTipTap);
    }
}
