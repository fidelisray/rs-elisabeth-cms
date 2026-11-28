<?php

namespace App\Filament\Resources\BannerPromotions\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BannerPromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make(__('Informasi Banner - Bahasa Indonesia (ID)'))
                    ->description(__('Judul dan identifier banner dalam Bahasa Indonesia.'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('title.id')
                            ->label(__('Judul Banner (Indonesia)'))
                            ->maxLength(255)
                            ->required()
                            ->clearErrorOnInput()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $operation, ?string $state) {
                                if (($operation === 'create' || empty($get('slug.id'))) && filled($state)) {
                                    $set('slug.id', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug.id')
                            ->label(__('Identifier / Slug (Indonesia)'))
                            ->placeholder(__('Klik pada form dan teks akan terisi otomatis'))
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->unique(column: 'slug->id', ignoreRecord: true)
                            ->validationMessages(['unique' => __('Identifier (Slug) sudah digunakan. Pilih identifier lain.')])
                            ->helperText(__('Dapat diedit jika diperlukan')),
                    ]),

                Section::make(__('Informasi Banner - Bahasa Inggris (EN)'))
                    ->description(__('Judul dan identifier banner dalam Bahasa Inggris.'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('title.en')
                            ->label(__('Judul Banner (English)'))
                            ->maxLength(255)
                            ->required()
                            ->clearErrorOnInput()
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
                            ->maxLength(255)
                            ->unique(column: 'slug->en', ignoreRecord: true)
                            ->validationMessages(['unique' => __('Identifier (Slug) sudah digunakan. Pilih identifier lain.')])
                            ->helperText(__('Dapat diedit jika diperlukan')),
                    ]),

                Section::make(__('Gambar Banner'))
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('image_path')
                            ->label(__('Gambar'))
                            ->helperText(__('Unggah gambar banner dengan format JPEG/JPG/PNG/WebP, ukuran maks 5 MB'))
                            ->disk(config('filesystems.media_disk'))
                            ->directory('banners')
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
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Publikasi'))
                    ->columnSpanFull()
                    ->description(__('Atur apakah Banner Promosi ini akan ditampilkan pada halaman website'))
                    ->schema([
                        Placeholder::make('trash_notice')
                            ->label(__('Penghapusan'))
                            ->content(__('Konten yang dihapus tidak tampil di website. Hanya Super Admin yang dapat memulihkannya.'))
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label(__('Aktif dan Tampilkan di halaman website'))
                            ->default(false)
                            ->helperText(__('Non-aktifkan untuk menyembunyikan banner promosi ini dari halaman website tanpa menghapus datanya'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
