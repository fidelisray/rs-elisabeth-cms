<?php

namespace App\Filament\Resources\OnlineLibraries\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class OnlineLibraryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Informasi Dokumen - Bahasa Indonesia (ID)'))
                    ->description(__('Lengkapi judul, kategori, dan deskripsi dokumen dalam Bahasa Indonesia.'))
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextInput::make('title.id')
                            ->label(__('Judul Dokumen (Indonesia)'))
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
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->placeholder(__('Contoh: Panduan Medis, Edukasi Kesehatan, SOP')),

                        RichEditor::make('description.id')
                            ->label(__('Deskripsi / Ringkasan Dokumen (Indonesia)'))
                            ->clearErrorOnInput()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Informasi Dokumen - Bahasa Inggris (EN)'))
                    ->description(__('Lengkapi judul, kategori, dan deskripsi dokumen dalam Bahasa Inggris.'))
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextInput::make('title.en')
                            ->label(__('Judul Dokumen (English)'))
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
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->placeholder(__('Contoh: Panduan Medis, Edukasi Kesehatan, SOP')),

                        RichEditor::make('description.en')
                            ->label(__('Deskripsi / Ringkasan Dokumen (English)'))
                            ->clearErrorOnInput()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('File Dokumen PDF'))
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('file_path')
                            ->label(__('Dokumen PDF'))
                            ->required()
                            ->clearErrorOnChange()
                            ->disk(config('filesystems.media_disk'))
                            ->directory('online-libraries')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(20480)
                            ->storeFileNamesIn('file_name')
                            ->helperText(__('Unggah dokumen berformat PDF dengan ukuran maksimal 20 MB.')),
                    ]),

                Section::make(__('Publikasi'))
                    ->columnSpanFull()
                    ->description(__('Atur apakah dokumen ini akan ditampilkan pada halaman perpustakaan online'))
                    ->schema([
                        Placeholder::make('trash_notice')
                            ->label(__('Penghapusan'))
                            ->content(__('Dokumen yang dihapus tidak tampil di website. Hanya Super Admin yang dapat memulihkannya.'))
                            ->columnSpanFull(),

                        Toggle::make('is_published')
                            ->label(__('Publikasikan Dokumen'))
                            ->default(false),
                    ]),
            ]);
    }
}
