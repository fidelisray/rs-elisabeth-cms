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
                ->label('Sinkronisasi API Internal RS')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->visible(fn (): bool => auth()->user()?->can('Update:Doctor') ?? false)
                ->requiresConfirmation()
                ->modalHeading('Sinkronisasi Data Dokter dari SIMRS')
                ->modalDescription('Sistem akan menarik data dokter terbaru dari API internal RS. Data profil marketing yang sudah Anda isi (Bio, Lokasi, Pengalaman, Pendidikan) dijamin aman dan tidak akan tertimpa.')
                ->modalSubmitActionLabel('Mulai Sinkronisasi')
                ->action(function (DoctorSyncService $syncService) {
                    $result = $syncService->syncFromMedInfras();

                    $status = $result['status'] ?? 'failed';
                    $notification = Notification::make()
                        ->title($status === 'success' ? 'Sinkronisasi Selesai' : 'Sinkronisasi '.$status)
                        ->body("{$result['total']} dokter valid diproses ({$result['created']} baru, {$result['updated']} diperbarui).".(! empty($result['failed_specialties']) ? ' Spesialisasi gagal: '.implode(', ', $result['failed_specialties']) : '').(! empty($result['excluded_specialties']) ? ' Spesialisasi dikecualikan: '.implode(', ', $result['excluded_specialties']) : ''));
                    if ($status === 'success') {
                        $notification->success();
                    } elseif ($status === 'partial') {
                        $notification->warning();
                    } else {
                        $notification->danger();
                    }
                    $notification->send();
                }),
        ];
    }
}
