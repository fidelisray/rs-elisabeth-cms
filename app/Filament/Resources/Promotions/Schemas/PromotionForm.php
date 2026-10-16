<?php

namespace App\Filament\Resources\Promotions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Promosi')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([

                        TextInput::make('title')
                            ->label('Nama Promosi')
                            ->placeholder('Promo Terbaru, Promo Perawatan..')
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
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->helperText('Dapat diedit jika diperlukan'),

                        Textarea::make('shorts')
                            ->label('Deskripsi Singkat')
                            ->placeholder('Deskripsi singkat mengenai promosi')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        RichEditor::make('description')
                            ->label('Deskripsi')
                            ->required()
                            ->columnSpanFull(),

                        DatePicker::make('start_date')
                            ->label('Tanggal Promo Dimulai')
                            ->required(),
                        DatePicker::make('end_date')
                            ->label('Tanggal Promo Berakhir')
                            ->required(),
                    ]),

                Section::make('Poster Promosi')
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('image_path')
                            ->label('Poster')
                            ->required()
                            ->disk(config('filesystems.media_disk'))
                            ->directory('promotions')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatioOptions(['4:5', '3:4'])
                        // ->imageAspectRatio('4:5')
                        // ->automaticallyCropImagesToAspectRatio()
                            ->automaticallyResizeImagesToWidth(1200)
                            ->automaticallyResizeImagesToHeight(1500)
                            ->automaticallyResizeImagesMode('cover')
                            ->maxSize(5120) // 5 MB
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->helperText('Upload poster promosi dengan format JPEG/JPG/PNG/WebP, ukuran maks 5 MB'),
                    ]),

                Section::make('Publish')
                    ->columnSpanFull()
                    ->description('Atur apakah promotion ini akan ditampilkan pada halaman website')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Aktif dan Tampilkan di halaman website')
                            ->default(false)
                            ->helperText('Non-aktifkan untuk menyembunyikan promotion ini dari halaman website tanpa menghapus datanya')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
