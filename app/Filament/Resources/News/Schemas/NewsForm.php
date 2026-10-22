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
                Section::make('Konten Utama Berita')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([

                        TextInput::make('title')
                            ->label('Headline Berita')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $operation, ?string $state) {
                                if (($operation === 'create' || empty($get('slug'))) && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug')
                            ->label('Identifier')
                            ->placeholder('Klik pada form dan teks akan terisi otomatis')
                            ->required()
                            ->maxLength(255)
                            ->unique(column: fn ($livewire = null): string => 'slug->'.($livewire?->activeLocale ?? app()->getLocale()), ignoreRecord: true)
                            ->validationMessages(['unique' => 'Identifier (Slug) sudah digunakan. Pilih identifier lain.'])
                            ->helperText('Dapat diedit jika diperlukan'),

                        TextInput::make('category')
                            ->label('Kategori')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Masukkan kategori sesuai bahasa yang dipilih'),

                        TextInput::make('author')
                            ->label('Author')
                            ->required()
                            ->maxLength(25)
                            ->columnSpanFull(),

                        Textarea::make('shorts')
                            ->label('Ringkasan Berita')
                            ->placeholder('Ringkasan singkat yang akan muncul di bawah headline')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        RichEditor::make('content')
                            ->label('Isi Konten Berita')
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Section::make('Thumbnail Berita')
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('image_path')
                            ->label('Gambar')
                            ->required()
                            ->disk(config('filesystems.media_disk'))
                            ->directory('news')
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
