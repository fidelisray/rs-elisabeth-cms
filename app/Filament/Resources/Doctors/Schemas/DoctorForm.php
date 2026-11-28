<?php

namespace App\Filament\Resources\Doctors\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DoctorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Identitas Resmi Dokter (API Internal RS)'))
                    ->description(__('Data identitas bersumber dari API internal RS (Read-Only) dan disinkronkan secara otomatis.'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Nama Lengkap & Gelar'))
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('paramedic_code')
                            ->label(__('Kode Paramedis (ID SIMRS)'))
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('specialty_name')
                            ->label(__('Spesialisasi'))
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('service_unit_name')
                            ->label(__('Unit / Poliklinik Layanan'))
                            ->disabled()
                            ->dehydrated(false),
                    ]),

                Section::make(__('Konten Profil Dokter - Bahasa Indonesia (ID)'))
                    ->description(__('Lengkapi 4 data marketing dokter dalam Bahasa Indonesia untuk ditampilkan pada profil dokter di website RS Elisabeth.'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Textarea::make('bio.id')
                            ->label(__('Biografi / Tentang Dokter (Indonesia)'))
                            ->placeholder(__('Dokter berpengalaman yang siap memberikan pelayanan kesehatan terbaik...'))
                            ->rows(4)
                            ->clearErrorOnInput()
                            ->columnSpanFull(),

                        TextInput::make('location.id')
                            ->label(__('Data Lokasi Praktik (Indonesia)'))
                            ->placeholder('Semarang, Indonesia')
                            ->clearErrorOnInput(),

                        TextInput::make('experience.id')
                            ->label(__('Data Pengalaman (Indonesia)'))
                            ->placeholder(__('Lebih Dari 5 Tahun'))
                            ->clearErrorOnInput(),

                        TextInput::make('education.id')
                            ->label(__('Data Riwayat Pendidikan (Indonesia)'))
                            ->placeholder(__('Universitas Terkemuka'))
                            ->clearErrorOnInput()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Konten Profil Dokter - Bahasa Inggris (EN)'))
                    ->description(__('Lengkapi 4 data marketing dokter dalam Bahasa Inggris untuk ditampilkan pada profil dokter di website RS Elisabeth.'))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Textarea::make('bio.en')
                            ->label(__('Biografi / Tentang Dokter (English)'))
                            ->placeholder(__('Dokter berpengalaman yang siap memberikan pelayanan kesehatan terbaik...'))
                            ->rows(4)
                            ->clearErrorOnInput()
                            ->columnSpanFull(),

                        TextInput::make('location.en')
                            ->label(__('Data Lokasi Praktik (English)'))
                            ->placeholder('Semarang, Indonesia')
                            ->clearErrorOnInput(),

                        TextInput::make('experience.en')
                            ->label(__('Data Pengalaman (English)'))
                            ->placeholder(__('Lebih Dari 5 Tahun'))
                            ->clearErrorOnInput(),

                        TextInput::make('education.en')
                            ->label(__('Data Riwayat Pendidikan (English)'))
                            ->placeholder(__('Universitas Terkemuka'))
                            ->clearErrorOnInput()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Status Tampil di Website'))
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('is_active')
                            ->label(__('Tampilkan Profil Dokter di Website'))
                            ->helperText(__('Jika dinonaktifkan, profil dokter tidak akan ditampilkan pada website publik.'))
                            ->default(true),
                    ]),
            ]);
    }
}
