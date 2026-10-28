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
                Section::make('Informasi Dokumen')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextInput::make('title')
                            ->label('Judul Dokumen')
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
                            ->label('Identifier (Slug)')
                            ->placeholder('Klik pada form dan teks akan terisi otomatis')
                            ->required()
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->unique(column: fn ($livewire = null): string => 'slug->'.($livewire?->activeLocale ?? app()->getLocale()), ignoreRecord: true)
                            ->validationMessages(['unique' => 'Identifier (Slug) sudah digunakan. Pilih identifier lain.'])
                            ->helperText('Dapat diedit jika diperlukan'),

                        TextInput::make('category')
                            ->label('Kategori')
                            ->clearErrorOnInput()
                            ->maxLength(255)
                            ->placeholder('Contoh: Panduan Medis, Edukasi Kesehatan, SOP'),

                        RichEditor::make('description')
                            ->label('Deskripsi / Ringkasan Dokumen')
                            ->clearErrorOnInput()
                            ->columnSpanFull(),
                    ]),

                Section::make('File Dokumen PDF')
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('file_path')
                            ->label('Dokumen PDF')
                            ->required()
                            ->clearErrorOnChange()
                            ->disk(config('filesystems.media_disk'))
                            ->directory('online-libraries')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(20480) // 20 MB
                            ->storeFileNamesIn('file_name')
                            ->helperText('Upload dokumen berformat PDF dengan ukuran maksimal 20 MB.'),
                    ]),

                Section::make('Publish')
                    ->columnSpanFull()
                    ->description('Atur apakah dokumen ini akan ditampilkan pada halaman perpustakaan online')
                    ->schema([
                        Placeholder::make('trash_notice')
                            ->label('Penghapusan')
                            ->content('Dokumen yang dihapus tidak tampil di website. Hanya Super Admin yang dapat memulihkannya.')
                            ->columnSpanFull(),

                        Toggle::make('is_published')
                            ->label('Publish Dokumen')
                            ->default(false),
                    ]),
            ]);
    }
}
