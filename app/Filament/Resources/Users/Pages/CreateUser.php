<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * User baru SELALU wajib mengganti password pada login pertama (K-8, K-9),
     * termasuk bila admin mengisi password sendiri: yang mengetahui password itu
     * adalah admin, bukan pemilik akun.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['must_change_password'] = true;

        return $data;
    }
}
