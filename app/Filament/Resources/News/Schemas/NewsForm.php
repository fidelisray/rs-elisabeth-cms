<?php

namespace App\Filament\Resources\News\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class NewsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Konten Berita - Bahasa Indonesia (ID)'))
                    ->description(__('Lengkapi judul, kategori, ringkasan, dan isi berita dalam Bahasa Indonesia.'))
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([

                        TextInput::make('title.id')
                            ->label(__('Headline Berita (Indonesia)'))
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
                            ->label(__('Identifier / Slug (Indonesia)'))
                            ->placeholder(__('Klik pada form dan teks akan terisi otomatis'))
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->unique(column: 'slug->id', ignoreRecord: true)
                            ->validationMessages(['unique' => __('Identifier (Slug) sudah digunakan. Pilih identifier lain.')])
                            ->helperText(__('Dapat diedit jika diperlukan')),

                        TextInput::make('category.id')
                            ->label(__('Kategori (Indonesia)'))
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->placeholder(__('Masukkan kategori dalam Bahasa Indonesia')),

                        Textarea::make('shorts.id')
                            ->label(__('Ringkasan Berita (Indonesia)'))
                            ->placeholder(__('Ringkasan singkat yang akan muncul di bawah headline'))
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        RichEditor::make('content.id')
                            ->label(__('Isi Konten Berita (Indonesia)'))
                            ->required()
                            ->clearErrorOnInput()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Konten Berita - Bahasa Inggris (EN)'))
                    ->description(__('Lengkapi judul, kategori, ringkasan, dan isi berita dalam Bahasa Inggris.'))
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([

                        TextInput::make('title.en')
                            ->label(__('Headline Berita (English)'))
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
                            ->label(__('Identifier / Slug (English)'))
                            ->placeholder(__('Klik pada form dan teks akan terisi otomatis'))
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->unique(column: 'slug->en', ignoreRecord: true)
                            ->validationMessages(['unique' => __('Identifier (Slug) sudah digunakan. Pilih identifier lain.')])
                            ->helperText(__('Dapat diedit jika diperlukan')),

                        TextInput::make('category.en')
                            ->label(__('Kategori (English)'))
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->placeholder(__('Masukkan kategori dalam Bahasa Inggris')),

                        Textarea::make('shorts.en')
                            ->label(__('Ringkasan Berita (English)'))
                            ->placeholder(__('Ringkasan singkat yang akan muncul di bawah headline'))
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        RichEditor::make('content.en')
                            ->label(__('Isi Konten Berita (English)'))
                            ->required()
                            ->clearErrorOnInput()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Penulis & Thumbnail Berita'))
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('author')
                            ->label(__('Penulis'))
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(25)
                            ->columnSpanFull(),

                        FileUpload::make('image_path')
                            ->label(__('Gambar'))
                            ->required()
                            ->clearErrorOnChange()
                            ->disk(config('filesystems.media_disk'))
                            ->directory('news')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatioOptions(['16:9'])
                            ->automaticallyResizeImagesToWidth(1920)
                            ->automaticallyResizeImagesToHeight(1080)
                            ->automaticallyResizeImagesMode('cover')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->helperText(__('Unggah gambar berita dengan format JPEG/JPG/PNG/WebP, ukuran maks 5 MB')),
                    ]),

                Section::make(__('Publikasi'))
                    ->columnSpanFull()
                    ->description(__('Atur apakah Berita ini akan ditampilkan pada halaman website'))
                    ->schema([
                        Placeholder::make('trash_notice')
                            ->label(__('Penghapusan'))
                            ->content(__('Konten yang dihapus tidak tampil di website. Hanya Super Admin yang dapat memulihkannya.'))
                            ->columnSpanFull(),

                        Toggle::make('is_published')
                            ->label(__('Aktif dan Tampilkan di halaman website'))
                            ->default(false)
                            ->helperText(__('Non-aktifkan untuk menyembunyikan berita ini dari halaman website tanpa menghapus datanya'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
