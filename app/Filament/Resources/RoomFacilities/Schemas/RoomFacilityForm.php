<?php

namespace App\Filament\Resources\RoomFacilities\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class RoomFacilityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Informasi Utama')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([

                        TextInput::make('name')
                            ->label('Nama Ruangan')
                            ->placeholder('President Suite, VIP, Kelas I...')
                            ->required()
                            ->maxLength(100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                // Auto-generate slug dari name
                                $set('slug', Str::slug($state));
                            }),

                        TextInput::make('slug')
                            ->label('Identifier')
                            ->placeholder('Klik pada form teks akan terisi otomatis')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->helperText('Dapat diedit jika diperlukan'),

                        Select::make('category')
                            ->label('Kategori Ruangan')
                            ->placeholder('Pilih Kategori Ruangan')
                            ->options([
                                'premium' => 'Premium (President Suite, Suites, Executive)',
                                'standard' => 'Standard (VIP, Kelas I, II, III)',
                            ])
                            ->required()
                            ->helperText('Menentukan di section mana ruangan ini akan ditampilkan'),
                    ]),

                Section::make('Deskripsi Ruangan')
                    ->columnSpanFull()
                    ->schema([

                        Textarea::make('tagline')
                            ->label('Tagline / Deskripsi Singkat')
                            ->required()
                            ->placeholder('Deskripsi singkat sebagai Highlight untuk ruangan ini...')
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Deskripsi Lengkap')
                            ->required()
                            ->placeholder('Deskripsi lengkap ruangan terkait fasilitas, keunggulan, atau layanan lainnya...')
                            ->rows(5)
                            ->columnSpanFull(),
                    ]),

                Section::make('Foto Ruangan')
                    ->columnSpanFull()
                    ->description('Upload foto ruangan dengan format landscape (16:9)')
                    ->schema([

                        FileUpload::make('image_path')
                            ->label('Foto Ruangan')
                            ->disk('s3')
                            ->directory('room_facilities')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatioOptions(['16:9'])
                            // ->imageAspectRatio('16:9')
                            // ->automaticallyCropImagesToAspectRatio()

                            ->automaticallyResizeImagesToWidth(1920)
                            ->automaticallyResizeImagesToHeight(1080)
                            ->automaticallyResizeImagesMode('cover')
                            ->maxSize(5120) // 5 MB
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->required()
                            ->helperText('Upload foto dengan format JPEG/JPG/PNG/WebP, ukuran maksimal 5 MB, Resolusi ideal 1920x1080')
                            ->columnSpanFull(),
                    ]),

                Section::make('Daftar Fasilitas')
                    ->description('Kelompokkan fasilitas dalam grup. Contoh: Fasilitas Kamar, Fasilitas Tambahan, dll.')
                    ->schema([

                        Repeater::make('amenities')
                            ->label('Grup Fasilitas')
                            ->schema([

                                TextInput::make('group')
                                    ->label('Nama Grup')
                                    ->placeholder('Contoh: Kamar & Ruangan')
                                    ->required()
                                    ->maxLength(100)
                                    ->columnSpanFull(),

                                Repeater::make('items')
                                    ->required()
                                    ->label('Item Fasilitas')
                                    ->schema([

                                        TextInput::make('name')
                                            ->label('Nama Fasilitas')
                                            ->placeholder('Contoh: Air Conditioner (AC)')
                                            ->required()
                                            ->maxLength(100),

                                        TextInput::make('value')
                                            ->label('Spesifikasi (Opsional)')
                                            ->placeholder('contoh: 55" Smart TV, Central')
                                            // ->helperText('Biarkan kosong jika ini hanya fasilitas standar (tampil sebagai centang)')
                                            ->maxLength(255),

                                        Toggle::make('show_in_comparison')
                                            ->columnSpanFull()
                                            ->label('Hightlight Fasilitas')
                                            ->default(false)
                                            ->helperText('Aktifkan agar fasilitas ini ikut muncul di tabel perbandingan antar-ruangan'),
                                    ])
                                    ->columns(2)
                                    ->addActionLabel('+ Tambah Item')
                                    ->defaultItems(0)
                                    ->collapsible()
                                    ->columnSpanFull(),
                            ])
                            ->addActionLabel('+ Tambah Grup')
                            ->defaultItems(0)
                            ->collapsible()
                            ->columnSpanFull(),
                    ]),

                Section::make('Highlight Tags')
                    ->description('Highlight keunggulan utama yang dimiliki ruangan ini')
                    ->schema([

                        Repeater::make('highlight_tags')
                            ->label('Highlight Tags')
                            ->schema([

                                Select::make('icon')
                                    ->label('Pilih Ikon')
                                    ->options([
                                        'Fasilitas & Kenyamanan' => [
                                            'fa-solid fa-bed' => 'Tempat Tidur',
                                            'fa-solid fa-couch' => 'Sofa / Ruang Tamu',
                                            'fa-solid fa-bath' => 'Kamar Mandi',
                                            'fa-solid fa-snowflake' => 'AC / Pendingin Ruangan',
                                            'fa-solid fa-tv' => 'TV / Hiburan',
                                            'fa-solid fa-wifi' => 'WiFi / Internet',
                                            'fa-solid fa-mug-hot' => 'Dispenser / Teko Elektrik',
                                            'fa-solid fa-utensils' => 'Meja / Lemari Pribadi',
                                            'fa-solid fa-temperature-arrow-down' => 'Kulkas',
                                        ],
                                        'Pelayanan & Medis' => [
                                            'fa-solid fa-user-nurse' => 'Perawat 24 Jam',
                                            'fa-solid fa-stethoscope' => 'Peralatan Medis Lengkap',
                                            'fa-solid fa-heart-pulse' => 'Pemantauan Pasien',
                                            'fa-solid fa-bell' => 'Bel Panggilan Darurat',
                                            'fa-solid fa-wheelchair' => 'Akses Kursi Roda',
                                        ],
                                        'Keunggulan & Label' => [
                                            'fa-solid fa-crown' => 'Mahkota (Premium / VIP)',
                                            'fa-solid fa-star' => 'Bintang (Populer / Favorit)',
                                            'fa-solid fa-award' => 'Terbaik / Rekomendasi',
                                            'fa-solid fa-thumbs-up' => 'Sangat Direkomendasikan',
                                            'fa-solid fa-shield-heart' => 'Keamanan Ekstra',
                                            'fa-solid fa-hands-holding-child' => 'Ramah Anak & Keluarga',
                                        ],
                                    ])
                                    ->searchable()
                                    ->required(),

                                TextInput::make('label')
                                    ->label('Label Tag')
                                    ->placeholder('Contoh: Kamar Terluas')
                                    ->required()
                                    ->maxLength(100),
                            ])
                            ->columns(2)
                            ->addActionLabel('+ Tambah Highlight Tag')
                            ->defaultItems(0)
                            ->collapsible()
                            ->columnSpanFull(),
                    ]),

                Section::make('Spesifikasi Kamar')
                    ->columns(3)
                    ->schema([

                        TextInput::make('room_size')
                            ->label('Luas Kamar')
                            ->numeric()
                            ->suffix('m²')
                            ->placeholder('Contoh: 40')
                            ->maxValue(999),

                        TextInput::make('bed_count')
                            ->label('Jumlah Tempat Tidur')
                            ->numeric()
                            ->suffix('Bed')
                            ->placeholder('Contoh: 1')
                            ->maxValue(99),

                        TextInput::make('max_companion')
                            ->label('Maks. Penunggu')
                            ->numeric()
                            ->suffix('Orang')
                            ->placeholder('Contoh: 2')
                            ->maxValue(99),
                    ]),

                Section::make('Pesan Whatsapp')
                    // ->description('Pesan default yang akan dikirim oleh pengunjung ke admin WhatsApp')
                    ->schema([

                        TextInput::make('whatsapp_text')
                            ->label('Pesan Otomatis Yang Dikirim Pengunjung ke Admin')
                            ->placeholder('Contoh: Halo, saya ingin informasi Ruangan President Suite')
                            ->maxLength(255)
                            // ->helperText('Pesan ini akan menjadi default message saat pengunjung mengklik tombol WhatsApp di halaman ruangan ini.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Publish')
                    ->columnSpanFull()
                    ->description('Atur apakah ruangan ini akan ditampilkan pada halaman website')
                    ->columns(2)
                    ->schema([
                        Hidden::make('sort_order')
                            ->default(0),

                        Toggle::make('is_active')
                            ->label('Aktif dan Tampilkan di halaman website')
                            ->default(false)
                            ->helperText('Non-aktifkan untuk menyembunyikan ruangan ini dari halaman website tanpa menghapus datanya')
                            ->columnSpanFull(),
                    ]),

            ]);
    }
}
