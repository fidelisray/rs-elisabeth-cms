<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Schema;

class CustomProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent()->disabled(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    /**
     * Memanipulasi data sebelum disimpan ke database.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Jika user mengisi/mengganti password baru, ubah status wajib ganti password menjadi false
        if (! empty($data['password'])) {
            $data['must_change_password'] = false;
        }

        return $data;
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
