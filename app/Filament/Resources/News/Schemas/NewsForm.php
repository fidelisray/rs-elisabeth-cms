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
                Section::make('Konten Berita - Bahasa Indonesia (ID)')
                    ->description('Lengkapi judul, kategori, ringkasan, dan isi berita dalam Bahasa Indonesia.')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([

                        TextInput::make('title.id')
                            ->label('Headline Berita (Indonesia)')
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
                            ->placeholder('Klik pada form dan teks akan terisi otomatis')
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->unique(column: 'slug->id', ignoreRecord: true)
                            ->validationMessages(['unique' => 'Identifier (Slug) sudah digunakan. Pilih identifier lain.'])
                            ->helperText('Dapat diedit jika diperlukan'),

                        TextInput::make('category.id')
                            ->label('Kategori (Indonesia)')
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->placeholder('Masukkan kategori dalam Bahasa Indonesia'),

                        Textarea::make('shorts.id')
                            ->label('Ringkasan Berita (Indonesia)')
                            ->placeholder('Ringkasan singkat yang akan muncul di bawah headline')
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        RichEditor::make('content.id')
                            ->label('Isi Konten Berita (Indonesia)')
                            ->required()
                            ->clearErrorOnInput()
                            ->columnSpanFull(),
                    ]),

                Section::make('Konten Berita - Bahasa Inggris (EN)')
                    ->description('Lengkapi judul, kategori, ringkasan, dan isi berita dalam Bahasa Inggris.')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([

                        TextInput::make('title.en')
                            ->label('Headline Berita (English)')
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
                            ->placeholder('Klik pada form dan teks akan terisi otomatis')
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->unique(column: 'slug->en', ignoreRecord: true)
                            ->validationMessages(['unique' => 'Identifier (Slug) sudah digunakan. Pilih identifier lain.'])
                            ->helperText('Dapat diedit jika diperlukan'),

                        TextInput::make('category.en')
                            ->label('Kategori (English)')
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->placeholder('Masukkan kategori dalam Bahasa Inggris'),

                        Textarea::make('shorts.en')
                            ->label('Ringkasan Berita (English)')
                            ->placeholder('Short summary displayed below the headline')
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        RichEditor::make('content.en')
                            ->label('Isi Konten Berita (English)')
                            ->required()
                            ->clearErrorOnInput()
                            ->columnSpanFull(),
                    ]),

                Section::make('Penulis & Thumbnail Berita')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('author')
                            ->label('Author')
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(25)
                            ->columnSpanFull(),

                        FileUpload::make('image_path')
                            ->label('Gambar')
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
                            ->helperText('Upload gambar berita dengan format JPEG/JPG/PNG/WebP, ukuran maks 5 MB'),
                    ]),

                Section::make('Publish')
                    ->columnSpanFull()
                    ->description('Atur apakah Berita ini akan ditampilkan pada halaman website')
                    ->schema([
                        Placeholder::make('trash_notice')
                            ->label('Penghapusan')
                            ->content('Konten yang dihapus tidak tampil di website. Hanya Super Admin yang dapat memulihkannya.')
                            ->columnSpanFull(),

                        Toggle::make('is_published')
                            ->label('Aktif dan Tampilkan di halaman website')
                            ->default(false)
                            ->helperText('Non-aktifkan untuk menyembunyikan berita ini dari halaman website tanpa menghapus datanya')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
