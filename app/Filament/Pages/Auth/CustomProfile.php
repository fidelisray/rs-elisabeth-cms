<?php

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent()->clearErrorOnInput(),
                $this->getEmailFormComponent()->disabled(),
                Section::make(__('Keamanan Kata Sandi'))
                    ->description(__('Demi menjaga keamanan akun, seluruh pembaruan kata sandi dilakukan secara terpusat dengan sistem pengecekan kekuatan sandi.'))
                    ->schema([
                        Actions::make([
                            Action::make('changePassword')
                                ->label(__('Ganti Password'))
                                ->icon('heroicon-o-key')
                                ->color('primary')
                                ->button()
                                ->url(ForcePasswordChange::getUrl()),
                        ]),
                    ]),
                Section::make(__('Pemulihan Akses Darurat (CLI Break-Glass)'))
                    ->description(__('Demi keamanan standar enterprise, pemulihan darurat akun Super Admin tidak tersedia melalui antarmuka web. Jika akses terkunci, lakukan reset teraudit melalui konsol server dengan perintah php artisan app:super-admin:reset-password.'))
                    ->visible(fn (): bool => $this->getUser()->isSuperAdmin()),
            ]);
    }

    /**
     * Mengatur URL tujuan setelah profil berhasil disimpan.
     */
    protected function getRedirectUrl(): ?string
    {
        // Redirect kembali ke halaman Dasbor (home) Filament
        return filament()->getUrl();
    }
}
