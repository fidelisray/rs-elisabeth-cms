<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Lengkap')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('email')
                    ->label('Alamat Email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Akun Aktif')
                    ->helperText('Nonaktifkan untuk mencabut akses panel tanpa menghapus role. Akun bisa diaktifkan kembali kapan saja.')
                    ->default(true)
                    ->disabled(fn (?User $record): bool => $record?->isSuperAdmin() ?? false)
                    ->columnSpanFull(),

                Select::make('roles')
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => str($record->name)->headline())
                    ->preload()
                    ->label('Role / Jabatan')
                    ->required(),

                // Password hanya muncul saat Create, bukan Edit
                // Saat Edit, reset password dilakukan via aksi tabel
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->dehydrated(fn (?string $state, string $operation): bool => filled($state) || $operation === 'create')
                    ->dehydrateStateUsing(function (?string $state, string $operation) {
                        if (filled($state)) {
                            return Hash::make($state);
                        }
                        if ($operation === 'create') {
                            return Hash::make('123456');
                        }

                        return null;
                    })
                    ->required(false)
                    ->helperText('Kosongkan jika tidak ingin mengubah password. Untuk user baru, password sementara otomatis "123456". Pengguna wajib menggantinya saat login pertama.')
                    ->columnSpanFull(),
            ]);
    }
}
