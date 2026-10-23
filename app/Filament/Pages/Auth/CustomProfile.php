<?php

namespace App\Filament\Pages\Auth;

use App\Services\RecoveryCodeService;
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
                Section::make('Keamanan Kata Sandi')
                    ->description('Demi menjaga keamanan akun, seluruh pembaruan kata sandi dilakukan secara terpusat dengan sistem pengecekan kekuatan sandi.')
                    ->schema([
                        Actions::make([
                            Action::make('changePassword')
                                ->label('Ganti Password')
                                ->icon('heroicon-o-key')
                                ->color('primary')
                                ->button()
                                ->url(ForcePasswordChange::getUrl()),
                        ]),
                    ]),
                Section::make('Kode Pemulihan Darurat')
                    ->description(function (): string {
                        $remaining = app(RecoveryCodeService::class)->remainingCount($this->getUser());
                        $message = "Tersisa {$remaining} dari ".RecoveryCodeService::CODE_COUNT.' kode pemulihan sekali pakai.';

                        if ($remaining < 3) {
                            $message .= ' Peringatan: sisa kode pemulihan kurang dari 3. Segera buat ulang melalui terminal dengan perintah php artisan app:super-admin:recovery-codes:regenerate.';
                        }

                        return $message;
                    })
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
