<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

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
                            return \Illuminate\Support\Facades\Hash::make($state);
                        }
                        if ($operation === 'create') {
                            return \Illuminate\Support\Facades\Hash::make('123456');
                        }
                        return null;
                    })
                    ->required(false)
                    ->helperText('Kosongkan jika tidak ingin mengubah password. Untuk user baru, password default otomatis menjadi "123456".')
                    ->columnSpanFull(),
            ]);
    }
}

