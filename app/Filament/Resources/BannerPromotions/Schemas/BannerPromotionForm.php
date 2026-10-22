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

                Section::make('Informasi Dasar Banner Promosi')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Judul Banner')
                            // ->helperText('Digunakan sebagai atribut alt="" pada gambar (untuk SEO & aksesibilitas).')
                            ->maxLength(255)
                            ->required()
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

                    ]),

                Section::make('Gambar Banner')
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('image_path')
                            ->label('Gambar')
                            ->helperText('Upload gambar banner dengan format JPEG/JPG/PNG/WebP, ukuran maks 5 MB')
                            ->disk(config('filesystems.media_disk'))
                            ->directory('banners')
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
                            ->columnSpanFull(),
                    ]),

                Section::make('Publish')
                    ->columnSpanFull()
                    ->description('Atur apakah Banner Promosi ini akan ditampilkan pada halaman website')
                    ->schema([
                        Placeholder::make('trash_notice')
                            ->label('Penghapusan')
                            ->content('Konten yang dihapus tidak tampil di website. Hanya Super Admin yang dapat memulihkannya.')
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Aktif dan Tampilkan di halaman website')
                            ->default(false)
                            ->helperText('Non-aktifkan untuk menyembunyikan banner promosi ini dari halaman website tanpa menghapus datanya')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
