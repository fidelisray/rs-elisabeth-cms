<?php

namespace App\Filament\Resources\Promotions\Schemas;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
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
                            ->clearErrorOnInput()
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
                            ->clearErrorOnInput()
                            ->maxLength(100)
                            ->unique(column: fn ($livewire = null): string => 'slug->'.($livewire?->activeLocale ?? app()->getLocale()), ignoreRecord: true)
                            ->validationMessages(['unique' => 'Identifier (Slug) sudah digunakan. Pilih identifier lain.'])
                            ->helperText('Dapat diedit jika diperlukan'),

                        Textarea::make('shorts')
                            ->label('Deskripsi Singkat')
                            ->placeholder('Deskripsi singkat mengenai promosi')
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        RichEditor::make('description')
                            ->label('Deskripsi')
                            ->required()
                            ->clearErrorOnInput()
                            ->columnSpanFull(),

                        DatePicker::make('start_date')
                            ->label('Tanggal Promo Dimulai')
                            ->helperText('Boleh dikosongkan: promo langsung berlaku sejak diaktifkan.'),

                        DatePicker::make('end_date')
                            ->label('Tanggal Promo Berakhir')
                            ->afterOrEqual('start_date')
                            ->live()
                            ->helperText('Boleh dikosongkan. Promo berakhir pada tanggal ini (hari terakhir masih tampil).'),

                        Placeholder::make('end_date_warning')
                            ->hiddenLabel()
                            ->content('Tanggal berakhir kosong: promo ini akan tampil terus di website sampai Anda menonaktifkannya secara manual dengan toggle "Aktif".')
                            ->visible(fn (Get $get): bool => blank($get('end_date')))
                            ->columnSpanFull(),

                        // Konfirmasi wajib saat tanggal berakhir kosong. Bukan kolom database
                        // (dehydrated false); divalidasi di server, bukan hanya di browser.
                        Checkbox::make('confirm_no_end_date')
                            ->label('Saya memahami promo ini akan tampil terus sampai saya menonaktifkannya secara manual lewat toggle Aktif.')
                            ->visible(fn (Get $get): bool => blank($get('end_date')))
                            ->accepted(fn (Get $get): bool => blank($get('end_date')))
                            ->validationMessages(['accepted' => 'Centang konfirmasi ini bila tanggal berakhir dikosongkan.'])
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ]),

                Section::make('Poster Promosi')
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('image_path')
                            ->label('Poster')
                            ->required()
                            ->clearErrorOnChange()
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
                        Placeholder::make('trash_notice')
                            ->label('Penghapusan')
                            ->content('Konten yang dihapus tidak tampil di website. Hanya Super Admin yang dapat memulihkannya.')
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Aktif dan Tampilkan di halaman website')
                            ->default(false)
                            ->helperText('Non-aktifkan untuk menyembunyikan promotion ini dari halaman website tanpa menghapus datanya')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
