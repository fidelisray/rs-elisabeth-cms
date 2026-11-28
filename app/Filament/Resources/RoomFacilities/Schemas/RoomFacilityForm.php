<?php

namespace App\Filament\Resources\RoomFacilities\Schemas;

use App\Filament\Support\WebpUploadFieldHelper;
use App\Rules\ValidRoomNestedContent;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class RoomFacilityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make(__('Konten Ruangan - Bahasa Indonesia (ID)'))
                    ->description(__('Informasi utama, deskripsi, dan pesan WhatsApp ruangan dalam Bahasa Indonesia.'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([

                        TextInput::make('name.id')
                            ->label(__('Nama Ruangan (Indonesia)'))
                            ->placeholder(__('President Suite, VIP, Kelas I...'))
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $operation, ?string $state) {
                                // Slug hanya dibuat otomatis saat membuat data baru (atau bila slug
                                // kosong). Slug adalah bagian URL publik, jadi mengedit nama record
                                // lama tidak boleh mengubahnya (link web, bookmark, dan SEO).
                                if (($operation === 'create' || empty($get('slug.id'))) && filled($state)) {
                                    $set('slug.id', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug.id')
                            ->label(__('Identifier / Slug (Indonesia)'))
                            ->placeholder(__('Klik pada form dan teks akan terisi otomatis'))
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(100)
                            ->unique(column: 'slug->id', ignoreRecord: true)
                            ->validationMessages(['unique' => __('Identifier (Slug) sudah digunakan. Pilih identifier lain.')])
                            ->helperText(__('Dapat diedit jika diperlukan')),

                        Textarea::make('tagline.id')
                            ->label(__('Tagline / Deskripsi Singkat (Indonesia)'))
                            ->required()
                            ->clearErrorOnInput()
                            ->placeholder(__('Deskripsi singkat sebagai highlight untuk ruangan ini...'))
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpanFull(),

                        Textarea::make('description.id')
                            ->label(__('Deskripsi Lengkap (Indonesia)'))
                            ->required()
                            ->clearErrorOnInput()
                            ->placeholder(__('Deskripsi lengkap ruangan terkait fasilitas, keunggulan, atau layanan lainnya...'))
                            ->rows(5)
                            ->columnSpanFull(),

                        TextInput::make('whatsapp_text.id')
                            ->label(__('Pesan Otomatis WhatsApp (Indonesia - Opsional)'))
                            ->placeholder(__('Contoh: Halo, saya ingin informasi Ruangan President Suite'))
                            ->maxLength(255)
                            ->clearErrorOnInput()
                            ->helperText(__('Isi kedua bahasa (Indonesia & English) jika pesan otomatis digunakan, atau kosongkan keduanya.'))
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Konten Ruangan - Bahasa Inggris (EN)'))
                    ->description(__('Informasi utama, deskripsi, dan pesan WhatsApp ruangan dalam Bahasa Inggris.'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([

                        TextInput::make('name.en')
                            ->label(__('Nama Ruangan (English)'))
                            ->placeholder(__('Contoh: President Suite, VIP Room, Class I...'))
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $operation, ?string $state) {
                                if (($operation === 'create' || empty($get('slug.en'))) && filled($state)) {
                                    $set('slug.en', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug.en')
                            ->label(__('Identifier / Slug (English)'))
                            ->placeholder(__('Klik pada form dan teks akan terisi otomatis'))
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(100)
                            ->unique(column: 'slug->en', ignoreRecord: true)
                            ->validationMessages(['unique' => __('Identifier (Slug) sudah digunakan. Pilih identifier lain.')])
                            ->helperText(__('Dapat diedit jika diperlukan')),

                        Textarea::make('tagline.en')
                            ->label(__('Tagline / Deskripsi Singkat (English)'))
                            ->required()
                            ->clearErrorOnInput()
                            ->placeholder(__('Deskripsi singkat sebagai highlight untuk ruangan ini...'))
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpanFull(),

                        Textarea::make('description.en')
                            ->label(__('Deskripsi Lengkap (English)'))
                            ->required()
                            ->clearErrorOnInput()
                            ->placeholder(__('Deskripsi lengkap ruangan terkait fasilitas, keunggulan, atau layanan lainnya...'))
                            ->rows(5)
                            ->columnSpanFull(),

                        TextInput::make('whatsapp_text.en')
                            ->label(__('Pesan Otomatis WhatsApp (English - Opsional)'))
                            ->placeholder(__('Contoh: Hello, I would like information about the President Suite room'))
                            ->maxLength(255)
                            ->clearErrorOnInput()
                            ->helperText(__('Isi kedua bahasa (Indonesia & English) jika pesan otomatis digunakan, atau kosongkan keduanya.'))
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Foto Ruangan'))
                    ->columnSpanFull()
                    ->description(__('Unggah foto ruangan dengan format landscape (16:9)'))
                    ->schema([

                        FileUpload::make('image_path')
                            ->label(__('Foto Ruangan'))
                            ->disk(config('filesystems.media_disk'))
                            ->directory('room_facilities')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatioOptions(['16:9'])
                            ->automaticallyResizeImagesToWidth(1920)
                            ->automaticallyResizeImagesToHeight(1080)
                            ->automaticallyResizeImagesMode('cover')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->required()
                            ->clearErrorOnChange()
                            ->helperText(WebpUploadFieldHelper::helperText('1920x1080'))
                            ->columnSpanFull(),

                        WebpUploadFieldHelper::statusPlaceholder(),
                    ]),

                // Kelompok fasilitas menggunakan grup tetap:
                // Nama Grup — Indonesia: Fasilitas Utama & Fasilitas Tambahan
                // Nama Grup — English: Primary Facilities & Supplementary Facilities
                Section::make(__('Daftar Fasilitas'))
                    ->description(__('Kelompok fasilitas terbagi atas Fasilitas Utama (wajib min. 1 item jika fasilitas diisi) dan Fasilitas Tambahan (opsional).'))
                    ->columnSpanFull()
                    ->schema([

                        Section::make(__('Fasilitas Utama'))
                            ->description(__('Fasilitas pokok yang wajib tersedia pada ruangan ini.'))
                            ->schema([
                                Repeater::make('main_amenities')
                                    ->label(__('Item Fasilitas Utama'))
                                    ->rule(function (Get $get) {
                                        return function (string $attribute, $value, \Closure $fail) use ($get) {
                                            $additional = $get('additional_amenities');
                                            if (! empty($additional) && empty($value)) {
                                                $fail(__('Fasilitas Utama wajib diisi minimal 1 item jika Fasilitas Tambahan diisi.'));
                                            }
                                        };
                                    })
                                    ->schema([
                                        TextInput::make('name.id')
                                            ->label(__('Nama Fasilitas — Indonesia'))
                                            ->placeholder(__('Contoh: Air Conditioner (AC)'))
                                            ->required()
                                            ->clearErrorOnInput()
                                            ->maxLength(100),

                                        TextInput::make('name.en')
                                            ->label(__('Nama Fasilitas — English'))
                                            ->placeholder(__('Contoh: Air Conditioner (AC)'))
                                            ->required()
                                            ->clearErrorOnInput()
                                            ->maxLength(100),

                                        TextInput::make('value.id')
                                            ->label(__('Spesifikasi — Indonesia (Opsional)'))
                                            ->placeholder(__('Contoh: 55" Smart TV, Central'))
                                            ->maxLength(255),

                                        TextInput::make('value.en')
                                            ->label(__('Spesifikasi — English (Opsional)'))
                                            ->helperText(__('Isi kedua bahasa jika spesifikasi digunakan, atau kosongkan keduanya.'))
                                            ->maxLength(255),

                                        Toggle::make('show_in_comparison')
                                            ->columnSpanFull()
                                            ->label(__('Highlight Fasilitas'))
                                            ->default(false)
                                            ->helperText(__('Aktifkan agar fasilitas ini ikut muncul di tabel perbandingan antar-ruangan')),
                                    ])
                                    ->columns(2)
                                    ->addActionLabel(__('+ Tambah Fasilitas Utama'))
                                    ->defaultItems(0)
                                    ->collapsible()
                                    ->columnSpanFull(),
                            ]),

                        Section::make(__('Fasilitas Tambahan'))
                            ->description(__('Fasilitas pelengkap atau ekstra (opsional).'))
                            ->schema([
                                Repeater::make('additional_amenities')
                                    ->label(__('Item Fasilitas Tambahan'))
                                    ->schema([
                                        TextInput::make('name.id')
                                            ->label(__('Nama Fasilitas — Indonesia'))
                                            ->placeholder(__('Contoh: Kursi Penunggu'))
                                            ->required()
                                            ->clearErrorOnInput()
                                            ->maxLength(100),

                                        TextInput::make('name.en')
                                            ->label(__('Nama Fasilitas — English'))
                                            ->placeholder(__('Contoh: Companion Chair'))
                                            ->required()
                                            ->clearErrorOnInput()
                                            ->maxLength(100),

                                        TextInput::make('value.id')
                                            ->label(__('Spesifikasi — Indonesia (Opsional)'))
                                            ->placeholder(__('Contoh: Meja Kerja'))
                                            ->maxLength(255),

                                        TextInput::make('value.en')
                                            ->label(__('Spesifikasi — English (Opsional)'))
                                            ->helperText(__('Isi kedua bahasa jika spesifikasi digunakan, atau kosongkan keduanya.'))
                                            ->maxLength(255),

                                        Toggle::make('show_in_comparison')
                                            ->columnSpanFull()
                                            ->label(__('Highlight Fasilitas'))
                                            ->default(false)
                                            ->helperText(__('Aktifkan agar fasilitas ini ikut muncul di tabel perbandingan antar-ruangan')),
                                    ])
                                    ->columns(2)
                                    ->addActionLabel(__('+ Tambah Fasilitas Tambahan'))
                                    ->defaultItems(0)
                                    ->collapsible()
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make(__('Highlight Tags'))
                    ->description(__('Highlight keunggulan utama yang dimiliki ruangan ini'))
                    ->schema([

                        Repeater::make('highlight_tags')
                            ->label(__('Highlight Tags'))
                            ->rules([new ValidRoomNestedContent('highlight_tags')])
                            ->schema([

                                Select::make('icon')
                                    ->label(__('Pilih Ikon'))
                                    ->options(fn (): array => [
                                        __('Fasilitas & Kenyamanan') => [
                                            'fa-solid fa-bed' => __('Tempat Tidur'),
                                            'fa-solid fa-couch' => __('Sofa / Ruang Tamu'),
                                            'fa-solid fa-bath' => __('Kamar Mandi'),
                                            'fa-solid fa-snowflake' => __('AC / Pendingin Ruangan'),
                                            'fa-solid fa-tv' => __('TV / Hiburan'),
                                            'fa-solid fa-wifi' => __('WiFi / Internet'),
                                            'fa-solid fa-mug-hot' => __('Dispenser / Teko Elektrik'),
                                            'fa-solid fa-utensils' => __('Meja / Lemari Pribadi'),
                                            'fa-solid fa-temperature-arrow-down' => __('Kulkas'),
                                        ],
                                        __('Pelayanan & Medis') => [
                                            'fa-solid fa-user-nurse' => __('Perawat 24 Jam'),
                                            'fa-solid fa-stethoscope' => __('Peralatan Medis Lengkap'),
                                            'fa-solid fa-heart-pulse' => __('Pemantauan Pasien'),
                                            'fa-solid fa-bell' => __('Bel Panggilan Darurat'),
                                            'fa-solid fa-wheelchair' => __('Akses Kursi Roda'),
                                        ],
                                        __('Keunggulan & Label') => [
                                            'fa-solid fa-crown' => __('Mahkota (Premium / VIP)'),
                                            'fa-solid fa-star' => __('Bintang (Populer / Favorit)'),
                                            'fa-solid fa-award' => __('Terbaik / Rekomendasi'),
                                            'fa-solid fa-thumbs-up' => __('Sangat Direkomendasikan'),
                                            'fa-solid fa-shield-heart' => __('Keamanan Ekstra'),
                                            'fa-solid fa-hands-holding-child' => __('Ramah Anak & Keluarga'),
                                        ],
                                    ])
                                    ->searchable()
                                    ->required()
                                    ->clearErrorOnChange(),

                                TextInput::make('label.id')
                                    ->label(__('Label Tag — Indonesia'))
                                    ->placeholder(__('Contoh: Kamar Terluas'))
                                    ->required()
                                    ->clearErrorOnInput()
                                    ->maxLength(100),

                                TextInput::make('label.en')
                                    ->label(__('Label Tag — English'))
                                    ->required()
                                    ->clearErrorOnInput()
                                    ->maxLength(100),
                            ])
                            ->columns(2)
                            ->addActionLabel(__('+ Tambah Highlight Tag'))
                            ->defaultItems(0)
                            ->collapsible()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Spesifikasi Kamar'))
                    ->columns(2)
                    ->schema([

                        // Field room_size (sebelumnya suffix('m²')) disembunyikan dan diset null.
                        Hidden::make('room_size')
                            ->default(null)
                            ->dehydrateStateUsing(fn () => null),

                        TextInput::make('bed_count')
                            ->label(__('Jumlah Tempat Tidur'))
                            ->numeric()
                            ->suffix(__('Tempat Tidur'))
                            ->placeholder(__('Contoh: 1'))
                            ->maxValue(99),

                        TextInput::make('max_companion')
                            ->label(__('Maks. Penunggu'))
                            ->numeric()
                            ->suffix(__('Orang'))
                            ->placeholder(__('Contoh: 2'))
                            ->maxValue(99),
                    ]),

                Section::make(__('Publikasi'))
                    ->columnSpanFull()
                    ->description(__('Atur apakah ruangan ini akan ditampilkan pada halaman website'))
                    ->columns(2)
                    ->schema([
                        Placeholder::make('trash_notice')
                            ->label(__('Penghapusan'))
                            ->content(__('Konten yang dihapus tidak tampil di website. Hanya Super Admin yang dapat memulihkannya.'))
                            ->columnSpanFull(),

                        Hidden::make('sort_order')
                            ->default(0),

                        Toggle::make('is_active')
                            ->label(__('Aktif dan Tampilkan di halaman website'))
                            ->default(false)
                            ->helperText(__('Non-aktifkan untuk menyembunyikan ruangan ini dari halaman website tanpa menghapus datanya'))
                            ->columnSpanFull(),
                    ]),

            ]);
    }
}
