<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UsersTable
{
    /**
     * Aksi aktif/nonaktif hanya boleh dilakukan super_admin, tidak pada diri
     * sendiri, dan tidak pada akun super_admin.
     */
    private static function canToggle(User $record): bool
    {
        $actor = Auth::user();

        return $actor !== null
            && $actor->isSuperAdmin()
            && $actor->getKey() !== $record->getKey()
            && ! $record->isSuperAdmin();
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Nama'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('roles.name')
                    ->label(__('model.role'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'marketing' => 'info',
                        'Staff IT' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'super_admin' => 'Super Admin',
                        'marketing' => 'Marketing',
                        'Staff IT' => 'Staff IT',
                        default => $state,
                    }),

                IconColumn::make('is_active')
                    ->label(__('Aktif'))
                    ->boolean()
                    ->sortable(),

                IconColumn::make('must_change_password')
                    ->label(__('Wajib Ganti Password?'))
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('success'),

                TextColumn::make('created_at')
                    ->label(__('Dibuat'))
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),

                Action::make('deactivate')
                    ->label(__('Nonaktifkan'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('Nonaktifkan Akun'))
                    ->modalDescription(fn ($record): string => __('Akses panel :name akan dicabut. Role tetap tersimpan dan akun dapat diaktifkan kembali.', ['name' => $record->name]))
                    ->visible(fn ($record): bool => $record->is_active && self::canToggle($record))
                    ->action(function ($record) {
                        $record->update(['is_active' => false]);

                        Notification::make()
                            ->success()
                            ->title(__('Akun dinonaktifkan'))
                            ->body(__(':name tidak dapat lagi mengakses panel.', ['name' => $record->name]))
                            ->send();
                    }),

                Action::make('activate')
                    ->label(__('Aktifkan'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('Aktifkan Akun'))
                    ->modalDescription(fn ($record): string => __('Akses panel :name akan dipulihkan sesuai role yang dimiliki.', ['name' => $record->name]))
                    ->visible(fn ($record): bool => ! $record->is_active && self::canToggle($record))
                    ->action(function ($record) {
                        $record->update(['is_active' => true]);

                        Notification::make()
                            ->success()
                            ->title(__('Akun diaktifkan'))
                            ->body(__(':name dapat kembali mengakses panel.', ['name' => $record->name]))
                            ->send();
                    }),

                Action::make('resetPassword')
                    ->label(__('Reset Password'))
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    // Reset ke password default hanya untuk staf. Password super_admin
                    // tidak pernah direset ke nilai yang diketahui publik (K-6).
                    ->visible(fn ($record): bool => ! $record->isSuperAdmin())
                    ->requiresConfirmation()
                    ->modalHeading(__('Reset Password Pengguna'))
                    ->modalDescription(fn ($record): string => __('Apakah Anda yakin ingin mereset password :name ke password default "123456"?', ['name' => $record->name]))
                    ->action(function ($record) {
                        $record->update([
                            'password' => Hash::make('123456'),
                            'must_change_password' => true,
                        ]);

                        Notification::make()
                            ->success()
                            ->title(__('Password Berhasil Direset'))
                            ->body(__('Password :name telah direset ke password sementara. Pengguna wajib menggantinya saat login berikutnya.', ['name' => $record->name]))
                            ->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}
