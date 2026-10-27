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

                Section::make('Konten Profil Dokter (Marketing)')
                    ->description('Lengkapi 4 data di bawah ini untuk ditampilkan pada modal dokter di website RS Elisabeth. Gunakan Locale Switcher di kanan atas untuk mengisi terjemahan Bahasa Inggris.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Textarea::make('bio')
                            ->label('Biografi / Tentang Dokter')
                            ->placeholder('Dokter berpengalaman yang siap memberikan pelayanan kesehatan terbaik...')
                            ->rows(4)
                            ->clearErrorOnInput()
                            ->columnSpanFull(),

                        TextInput::make('location')
                            ->label('Data Lokasi Praktik')
                            ->placeholder('Semarang, Indonesia')
                            ->clearErrorOnInput(),

                        TextInput::make('experience')
                            ->label('Data Pengalaman')
                            ->placeholder('Lebih Dari 5 Tahun')
                            ->clearErrorOnInput(),

                        TextInput::make('education')
                            ->label('Data Riwayat Pendidikan')
                            ->placeholder('Universitas Terkemuka')
                            ->clearErrorOnInput()
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Tampilkan Profil Dokter di Website')
                            ->helperText('Jika dinonaktifkan, profil dokter tidak akan ditampilkan pada website publik.')
                            ->default(true),
                    ]),
            ]);
    }
}
