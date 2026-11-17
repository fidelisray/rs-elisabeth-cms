<?php

namespace App\Filament\Resources\Articles\Schemas;

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

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Konten Artikel - Bahasa Indonesia (ID)')
                    ->description('Lengkapi judul, kategori, ringkasan, dan isi artikel dalam Bahasa Indonesia.')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([

                        TextInput::make('title.id')
                            ->label('Judul Artikel (Indonesia)')
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

                        TextInput::make('tags.id')
                            ->label('Kategori (Indonesia)')
                            ->placeholder('Masukkan kategori dalam Bahasa Indonesia')
                            ->maxLength(100)
                            ->required()
                            ->clearErrorOnInput(),

                        Textarea::make('shorts.id')
                            ->label('Ringkasan Artikel (Indonesia)')
                            ->maxLength(250)
                            ->columnSpanFull()
                            ->required()
                            ->clearErrorOnInput(),

                        RichEditor::make('content.id')
                            ->label('Isi Konten Artikel (Indonesia)')
                            ->columnSpanFull()
                            ->required()
                            ->clearErrorOnInput(),
                    ]),

                Section::make('Konten Artikel - Bahasa Inggris (EN)')
                    ->description('Lengkapi judul, kategori, ringkasan, dan isi artikel dalam Bahasa Inggris.')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([

                        TextInput::make('title.en')
                            ->label('Judul Artikel (English)')
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

                        TextInput::make('tags.en')
                            ->label('Kategori (English)')
                            ->placeholder('Masukkan kategori dalam Bahasa Inggris')
                            ->maxLength(100)
                            ->required()
                            ->clearErrorOnInput(),

                        Textarea::make('shorts.en')
                            ->label('Ringkasan Artikel (English)')
                            ->maxLength(250)
                            ->columnSpanFull()
                            ->required()
                            ->clearErrorOnInput(),

                        RichEditor::make('content.en')
                            ->label('Isi Konten Artikel (English)')
                            ->columnSpanFull()
                            ->required()
                            ->clearErrorOnInput(),
                    ]),

                Section::make('Penulis & Thumbnail Artikel')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('author')
                            ->label('Author')
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(50)
                            ->columnSpanFull(),

                        FileUpload::make('image_path')
                            ->required()
                            ->clearErrorOnChange()
                            ->disk(config('filesystems.media_disk'))
                            ->directory('articles')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatioOptions(['16:9'])
                            ->automaticallyResizeImagesToWidth(1920)
                            ->automaticallyResizeImagesToHeight(1080)
                            ->automaticallyResizeImagesMode('cover')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->helperText('Upload thumbnail artikel dengan format JPEG/JPG/PNG/WebP, ukuran maks 5 MB'),
                    ]),

                Section::make('Publish')
                    ->columnSpanFull()
                    ->description('Atur apakah artikel ini akan ditampilkan pada halaman website')
                    ->schema([
                        Placeholder::make('trash_notice')
                            ->label('Penghapusan')
                            ->content('Konten yang dihapus tidak tampil di website. Hanya Super Admin yang dapat memulihkannya.')
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Aktif dan Tampilkan di halaman website')
                            ->default(false)
                            ->helperText('Non-aktifkan untuk menyembunyikan artikel ini dari halaman website tanpa menghapus datanya')
                            ->columnSpanFull(),
                    ]),

            ]);
    }
}
