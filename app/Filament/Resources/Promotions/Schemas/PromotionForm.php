<?php

namespace App\Filament\Resources\Promotions\Schemas;

use App\Filament\Support\WebpUploadFieldHelper;
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
                Section::make(__('Informasi Promosi - Bahasa Indonesia (ID)'))
                    ->description(__('Lengkapi nama, ringkasan, dan deskripsi promosi dalam Bahasa Indonesia.'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([

                        TextInput::make('title.id')
                            ->label(__('Nama Promosi (Indonesia)'))
                            ->placeholder(__('Promo Terbaru, Promo Perawatan..'))
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
                            ->maxLength(100)
                            ->unique(column: 'slug->id', ignoreRecord: true)
                            ->validationMessages(['unique' => __('Identifier (Slug) sudah digunakan. Pilih identifier lain.')])
                            ->helperText(__('Dapat diedit jika diperlukan')),

                        Textarea::make('shorts.id')
                            ->label(__('Deskripsi Singkat (Indonesia)'))
                            ->placeholder(__('Deskripsi singkat mengenai promosi'))
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        RichEditor::make('description.id')
                            ->label(__('Deskripsi (Indonesia)'))
                            ->required()
                            ->clearErrorOnInput()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Informasi Promosi - Bahasa Inggris (EN)'))
                    ->description(__('Lengkapi nama, ringkasan, dan deskripsi promosi dalam Bahasa Inggris.'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([

                        TextInput::make('title.en')
                            ->label(__('Nama Promosi (English)'))
                            ->placeholder(__('Contoh: Latest Promo, Care Package Promo..'))
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
                            ->maxLength(100)
                            ->unique(column: 'slug->en', ignoreRecord: true)
                            ->validationMessages(['unique' => __('Identifier (Slug) sudah digunakan. Pilih identifier lain.')])
                            ->helperText(__('Dapat diedit jika diperlukan')),

                        Textarea::make('shorts.en')
                            ->label(__('Deskripsi Singkat (English)'))
                            ->placeholder(__('Deskripsi singkat mengenai promosi'))
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        RichEditor::make('description.en')
                            ->label(__('Deskripsi (English)'))
                            ->required()
                            ->clearErrorOnInput()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Periode & Poster Promosi'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        DatePicker::make('start_date')
                            ->label(__('Tanggal Promo Dimulai'))
                            ->helperText(__('Boleh dikosongkan: promo langsung berlaku sejak diaktifkan.')),

                        DatePicker::make('end_date')
                            ->label(__('Tanggal Promo Berakhir'))
                            ->afterOrEqual('start_date')
                            ->live()
                            ->helperText(__('Boleh dikosongkan. Promo berakhir pada tanggal ini (hari terakhir masih tampil).')),

                        Placeholder::make('end_date_warning')
                            ->hiddenLabel()
                            ->content(__('Tanggal berakhir kosong: promo ini akan tampil terus di website sampai Anda menonaktifkannya secara manual dengan toggle "Aktif".'))
                            ->visible(fn (Get $get): bool => blank($get('end_date')))
                            ->columnSpanFull(),

                        Checkbox::make('confirm_no_end_date')
                            ->label(__('Saya memahami promo ini akan tampil terus sampai saya menonaktifkannya secara manual lewat toggle Aktif.'))
                            ->visible(fn (Get $get): bool => blank($get('end_date')))
                            ->accepted(fn (Get $get): bool => blank($get('end_date')))
                            ->validationMessages(['accepted' => __('Centang konfirmasi ini bila tanggal berakhir dikosongkan.')])
                            ->dehydrated(false)
                            ->columnSpanFull(),

                        FileUpload::make('image_path')
                            ->label(__('Poster'))
                            ->required()
                            ->clearErrorOnChange()
                            ->disk(config('filesystems.media_disk'))
                            ->directory('promotions')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatioOptions(['4:5', '3:4'])
                            ->automaticallyResizeImagesToWidth(1200)
                            ->automaticallyResizeImagesToHeight(1500)
                            ->automaticallyResizeImagesMode('cover')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->helperText(WebpUploadFieldHelper::helperText('1200x1500'))
                            ->columnSpanFull(),

                        WebpUploadFieldHelper::statusPlaceholder(),
                    ]),

                Section::make(__('Publikasi'))
                    ->columnSpanFull()
                    ->description(__('Atur apakah promosi ini akan ditampilkan pada halaman website'))
                    ->schema([
                        Placeholder::make('trash_notice')
                            ->label(__('Penghapusan'))
                            ->content(__('Konten yang dihapus tidak tampil di website. Hanya Super Admin yang dapat memulihkannya.'))
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label(__('Aktif dan Tampilkan di halaman website'))
                            ->default(false)
                            ->helperText(__('Non-aktifkan untuk menyembunyikan promosi ini dari halaman website tanpa menghapus datanya'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
