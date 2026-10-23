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
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class FacilityServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Dasar')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, $get, ?string $operation, ?string $state) {
                                if (($operation === 'create' || empty($get('slug'))) && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug')
                            ->placeholder('Klik pada form, teks akan terisi otomatis')
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->unique(column: fn ($livewire = null): string => 'slug->'.($livewire?->activeLocale ?? app()->getLocale()), ignoreRecord: true)
                            ->validationMessages(['unique' => 'Identifier (Slug) sudah digunakan. Pilih identifier lain.'])
                            ->helperText('Dapat diedit jika diperlukan'),

                        Select::make('category')
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
                            ->clearErrorOnChange()
                            ->columnSpanFull(),

                        Textarea::make('short_description')
                            ->maxLength(255)
                            ->label('Deskripsi Singkat Fasilitas')
                            ->required()
                            ->clearErrorOnInput()
                            ->columnSpanFull()
                            ->helperText('Penjelasan fasilitas secara singkat dan padat'),

                        RichEditor::make('description')
                            ->label('Deskripsi Lengkap Fasilitas')
                            ->required()
                            ->clearErrorOnInput()
                            ->columnSpanFull()
                            ->helperText('Deskripsi lengkap yang menjelaskan fasilitas secara detil'),

                        TagsInput::make('highlights')
                            ->columnSpanFull()
                            ->label('Point Unggulan')
                            ->helperText('Masukkan poin-poin unggulan (tekan Enter/Koma untuk menambah). Akan muncul sebagai badge centang.'),
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
                            // ->imageEditorAspectRatioOptions([
                            //     '1:1',
                            //     '4:3',
                            //     '16:9'
                            // ])
                            ->automaticallyResizeImagesToWidth(1920)
                            ->automaticallyResizeImagesToHeight(1080)
                            ->automaticallyResizeImagesMode('cover')
                            ->maxSize(5120) // 5 MB
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->helperText('Upload gambar dengan format JPEG/JPG/PNG/WebP, ukuran maks 5 MB')
                            ->required()
                            ->clearErrorOnChange()
                            ->columnSpanFull(),
                    ]),

                Section::make('Call to Action (CTA)')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('wa_link_text')
                            ->label('Label Tombol WhatsApp')
                            ->placeholder('misal: Konsultasi Gizi')
                            ->maxLength(255),

                        TextInput::make('wa_number')
                            ->label('Nomor WhatsApp')
                            ->prefix('https://wa.me/')
                            ->placeholder('misal: 6285600600870')
                            ->maxLength(15)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                if (filled($state)) {
                                    // Auto-format "08..." to "628..." seamlessly
                                    $cleanNumber = preg_replace('/[^0-9]/', '', $state);
                                    if (str_starts_with($cleanNumber, '0')) {
                                        $cleanNumber = '62'.substr($cleanNumber, 1);
                                    }
                                    $set('wa_number', $cleanNumber);
                                }
                            }),

                        TextInput::make('wa_prefilled_message')
                            ->label('Pesan Otomatis (Prefilled)')
                            ->placeholder('misal: Halo, saya ingin mendaftar Konsultasi Gizi')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Toggle::make('has_appointment_cta')
                            ->label('Tampilkan Tombol "Buat Janji"?')
                            ->default(false)
                            ->helperText('Akan mengarah ke portal registrasi online.')
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
