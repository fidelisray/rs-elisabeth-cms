<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Role;
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
                    ->mutateStateForValidationUsing(fn (?string $state): string => User::normalizeEmail($state ?? ''))
                    ->dehydrateStateUsing(fn (?string $state): string => User::normalizeEmail($state ?? ''))
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
                    // super_admin tidak pernah bisa dipilih di form (K-3): akun itu hanya
                    // dibuat oleh app:super-admin:init, dan hanya ada satu.
                    ->relationship(
                        'roles',
                        'name',
                        modifyQueryUsing: fn ($query) => $query->where('name', '!=', Role::SUPER_ADMIN),
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record) => str($record->name)->headline())
                    ->preload()
                    ->label('Role / Jabatan')
                    // Role akun super_admin terkunci (K-3, K-7): tidak ditampilkan sebagai opsi,
                    // jadi pada record itu field tidak wajib, tidak bisa diubah, dan tidak disimpan.
                    ->disabled(fn (?User $record): bool => $record?->isSuperAdmin() ?? false)
                    ->dehydrated(fn (?User $record): bool => ! ($record?->isSuperAdmin() ?? false))
                    ->required(fn (?User $record): bool => ! ($record?->isSuperAdmin() ?? false)),

                // Password opsional saat mengedit akun staf biasa (bila diisi, pengguna wajib
                // mengganti password saat login berikutnya). Disembunyikan pada akun super_admin
                // agar perubahan password super_admin selalu melewati StrongNewPassword dan AuditLog.
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->hidden(fn (?User $record): bool => $record?->isSuperAdmin() ?? false)
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
