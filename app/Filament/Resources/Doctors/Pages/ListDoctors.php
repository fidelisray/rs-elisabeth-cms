<?php

namespace App\Filament\Resources\Doctors\Pages;

use App\Filament\Resources\Doctors\DoctorResource;
use App\Services\DoctorSyncService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;

class ListDoctors extends ListRecords
{
    use Translatable;

    protected static string $resource = DoctorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),

            Action::make('syncMedin')
                ->label('Sinkronisasi MedInfras')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Sinkronisasi Data Dokter dari SIMRS')
                ->modalDescription('Sistem akan menarik data dokter terbaru dari API MedInfras. Data profil marketing yang sudah Anda isi (Bio, Lokasi, Pengalaman, Pendidikan) dijamin aman dan tidak akan tertimpa.')
                ->modalSubmitActionLabel('Mulai Sinkronisasi')
                ->action(function (DoctorSyncService $syncService) {
                    $result = $syncService->syncFromMedInfras();

                    Notification::make()
                        ->title('Sinkronisasi Selesai')
                        ->body("Berhasil memproses {$result['total']} dokter ({$result['created']} data baru, {$result['updated']} data SIMRS diperbarui).")
                        ->success()
                        ->send();
                }),
        ];
    }
}
