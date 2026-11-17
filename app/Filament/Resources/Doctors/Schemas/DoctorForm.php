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
                Section::make('Identitas Resmi Dokter (API Internal RS)')
                    ->description('Data identitas bersumber dari API internal RS (Read-Only) dan disinkronkan secara otomatis.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Lengkap & Gelar')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('paramedic_code')
                            ->label('Kode Paramedis (ID SIMRS)')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('specialty_name')
                            ->label('Spesialisasi')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('service_unit_name')
                            ->label('Unit / Poliklinik Layanan')
                            ->disabled()
                            ->dehydrated(false),
                    ]),

                Section::make('Konten Profil Dokter - Bahasa Indonesia (ID)')
                    ->description('Lengkapi 4 data marketing dokter dalam Bahasa Indonesia untuk ditampilkan pada profil dokter di website RS Elisabeth.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Textarea::make('bio.id')
                            ->label('Biografi / Tentang Dokter (Indonesia)')
                            ->placeholder('Dokter berpengalaman yang siap memberikan pelayanan kesehatan terbaik...')
                            ->rows(4)
                            ->clearErrorOnInput()
                            ->columnSpanFull(),

                        TextInput::make('location.id')
                            ->label('Data Lokasi Praktik (Indonesia)')
                            ->placeholder('Semarang, Indonesia')
                            ->clearErrorOnInput(),

                        TextInput::make('experience.id')
                            ->label('Data Pengalaman (Indonesia)')
                            ->placeholder('Lebih Dari 5 Tahun')
                            ->clearErrorOnInput(),

                        TextInput::make('education.id')
                            ->label('Data Riwayat Pendidikan (Indonesia)')
                            ->placeholder('Universitas Terkemuka')
                            ->clearErrorOnInput()
                            ->columnSpanFull(),
                    ]),

                Section::make('Konten Profil Dokter - Bahasa Inggris (EN)')
                    ->description('Lengkapi 4 data marketing dokter dalam Bahasa Inggris untuk ditampilkan pada profil dokter di website RS Elisabeth.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Textarea::make('bio.en')
                            ->label('Biografi / Tentang Dokter (English)')
                            ->placeholder('Experienced doctor ready to provide the best healthcare services...')
                            ->rows(4)
                            ->clearErrorOnInput()
                            ->columnSpanFull(),

                        TextInput::make('location.en')
                            ->label('Data Lokasi Praktik (English)')
                            ->placeholder('Semarang, Indonesia')
                            ->clearErrorOnInput(),

                        TextInput::make('experience.en')
                            ->label('Data Pengalaman (English)')
                            ->placeholder('More Than 5 Years')
                            ->clearErrorOnInput(),

                        TextInput::make('education.en')
                            ->label('Data Riwayat Pendidikan (English)')
                            ->placeholder('Leading University')
                            ->clearErrorOnInput()
                            ->columnSpanFull(),
                    ]),

                Section::make('Status Tampil di Website')
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Tampilkan Profil Dokter di Website')
                            ->helperText('Jika dinonaktifkan, profil dokter tidak akan ditampilkan pada website publik.')
                            ->default(true),
                    ]),
            ]);
    }
}
