<?php

namespace App\Filament\Resources\FacilityServices\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class FacilityServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Konten Fasilitas - Bahasa Indonesia (ID)')
                    ->description('Lengkapi informasi fasilitas dan layanan dalam Bahasa Indonesia.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name.id')
                            ->label('Nama Fasilitas (Indonesia)')
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $operation, ?string $state) {
                                if (($operation === 'create' || empty($get('slug.id'))) && filled($state)) {
                                    $set('slug.id', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug.id')
                            ->label('Identifier / Slug (Indonesia)')
                            ->placeholder('Klik pada form, teks akan terisi otomatis')
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->unique(column: 'slug->id', ignoreRecord: true)
                            ->validationMessages(['unique' => 'Identifier (Slug) sudah digunakan. Pilih identifier lain.'])
                            ->helperText('Dapat diedit jika diperlukan'),

                        Textarea::make('short_description.id')
                            ->maxLength(255)
                            ->label('Deskripsi Singkat Fasilitas (Indonesia)')
                            ->required()
                            ->clearErrorOnInput()
                            ->columnSpanFull()
                            ->helperText('Penjelasan fasilitas secara singkat dan padat'),

                        RichEditor::make('description.id')
                            ->label('Deskripsi Lengkap Fasilitas (Indonesia)')
                            ->required()
                            ->clearErrorOnInput()
                            ->columnSpanFull()
                            ->helperText('Deskripsi lengkap yang menjelaskan fasilitas secara detil'),

                        TextInput::make('wa_link_text.id')
                            ->label('Label Tombol WhatsApp (Indonesia - Opsional)')
                            ->placeholder('misal: Konsultasi Gizi')
                            ->maxLength(255),

                        TextInput::make('wa_prefilled_message.id')
                            ->label('Pesan Otomatis WhatsApp (Indonesia - Opsional)')
                            ->placeholder('misal: Halo, saya ingin mendaftar Konsultasi Gizi')
                            ->maxLength(255),
                    ]),

                Section::make('Konten Fasilitas - Bahasa Inggris (EN)')
                    ->description('Lengkapi informasi fasilitas dan layanan dalam Bahasa Inggris.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name.en')
                            ->label('Nama Fasilitas (English)')
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $operation, ?string $state) {
                                if (($operation === 'create' || empty($get('slug.en'))) && filled($state)) {
                                    $set('slug.en', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug.en')
                            ->label('Identifier / Slug (English)')
                            ->placeholder('Klik pada form, teks akan terisi otomatis')
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->unique(column: 'slug->en', ignoreRecord: true)
                            ->validationMessages(['unique' => 'Identifier (Slug) sudah digunakan. Pilih identifier lain.'])
                            ->helperText('Dapat diedit jika diperlukan'),

                        Textarea::make('short_description.en')
                            ->maxLength(255)
                            ->label('Deskripsi Singkat Fasilitas (English)')
                            ->required()
                            ->clearErrorOnInput()
                            ->columnSpanFull()
                            ->helperText('Concise summary of the facility or service'),

                        RichEditor::make('description.en')
                            ->label('Deskripsi Lengkap Fasilitas (English)')
                            ->required()
                            ->clearErrorOnInput()
                            ->columnSpanFull()
                            ->helperText('Detailed description of the facility or service'),

                        TextInput::make('wa_link_text.en')
                            ->label('Label Tombol WhatsApp (English - Opsional)')
                            ->placeholder('e.g. Nutrition Consultation')
                            ->maxLength(255),

                        TextInput::make('wa_prefilled_message.en')
                            ->label('Pesan Otomatis WhatsApp (English - Opsional)')
                            ->placeholder('e.g. Hello, I would like to book a Nutrition Consultation')
                            ->maxLength(255),
                    ]),

                Section::make('Atribut Umum & Call to Action (CTA)')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('category')
                            ->label('Kategori Layanan')
                            ->options([
                                'Layanan Medis' => 'Layanan Medis',
                                'Layanan Penunjang' => 'Layanan Penunjang',
                                'Layanan 24 Jam' => 'Layanan 24 Jam',
                                'Layanan Spesialis' => 'Layanan Spesialis',
                                'Layanan Intensif' => 'Layanan Intensif',
                                'Diagnostik' => 'Diagnostik',
                                'Unggulan' => 'Unggulan',
                            ])
                            ->required()
                            ->clearErrorOnChange(),

                        TextInput::make('wa_number')
                            ->label('Nomor WhatsApp')
                            ->prefix('https://wa.me/')
                            ->placeholder('misal: 6285600600870')
                            ->maxLength(15)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                if (filled($state)) {
                                    $cleanNumber = preg_replace('/[^0-9]/', '', $state);
                                    if (str_starts_with($cleanNumber, '0')) {
                                        $cleanNumber = '62'.substr($cleanNumber, 1);
                                    }
                                    $set('wa_number', $cleanNumber);
                                }
                            }),

                        TagsInput::make('highlights')
                            ->columnSpanFull()
                            ->label('Point Unggulan')
                            ->helperText('Masukkan poin-poin unggulan (tekan Enter/Koma untuk menambah). Akan muncul sebagai badge centang.'),

                        Toggle::make('has_appointment_cta')
                            ->label('Tampilkan Tombol "Buat Janji"?')
                            ->default(false)
                            ->helperText('Akan mengarah ke portal registrasi online.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Gambar Fasilitas')
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('image_path')
                            ->label('Gambar')
                            ->disk(config('filesystems.media_disk'))
                            ->directory('facility_services')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatioOptions([
                                '16:9',
                            ])
                            ->automaticallyResizeImagesToWidth(1920)
                            ->automaticallyResizeImagesToHeight(1080)
                            ->automaticallyResizeImagesMode('cover')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->helperText('Upload gambar dengan format JPEG/JPG/PNG/WebP, ukuran maks 5 MB')
                            ->required()
                            ->clearErrorOnChange()
                            ->columnSpanFull(),
                    ]),

                Section::make('Publish')
                    ->columnSpanFull()
                    ->description('Atur apakah fasilitas ini akan ditampilkan pada halaman website')
                    ->columns(2)
                    ->schema([
                        Placeholder::make('trash_notice')
                            ->label('Penghapusan')
                            ->content('Konten yang dihapus tidak tampil di website. Hanya Super Admin yang dapat memulihkannya.')
                            ->columnSpanFull(),

                        Hidden::make('sort_order')
                            ->default(0),

                        Toggle::make('is_active')
                            ->label('Aktif dan Tampilkan di halaman website')
                            ->default(false)
                            ->helperText('Non-aktifkan untuk menyembunyikan fasilitas ini dari halaman website tanpa menghapus datanya')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
