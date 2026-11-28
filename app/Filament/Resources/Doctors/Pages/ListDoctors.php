<?php

namespace App\Filament\Resources\Doctors\Pages;

use App\Filament\Resources\Doctors\DoctorResource;
use App\Services\DoctorSyncService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;

class ListDoctors extends ListRecords
{
    use Translatable;

    protected static string $resource = DoctorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncMedin')
                ->label(__('Sinkronisasi API Internal RS'))
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->visible(fn (): bool => auth()->user()?->can('Update:Doctor') ?? false)
                ->requiresConfirmation()
                ->modalHeading(__('Sinkronisasi Data Dokter dari SIMRS'))
                ->modalDescription(__('Sistem akan menarik data dokter terbaru dari API internal RS. Data profil marketing yang sudah Anda isi (Bio, Lokasi, Pengalaman, Pendidikan) dijamin aman dan tidak akan tertimpa.'))
                ->modalSubmitActionLabel(__('Mulai Sinkronisasi'))
                ->action(function (DoctorSyncService $syncService) {
                    $result = $syncService->syncFromMedInfras();

                    $status = $result['status'] ?? 'failed';
                    $body = __(':total dokter valid diproses (:created baru, :updated diperbarui).', [
                        'total' => $result['total'],
                        'created' => $result['created'],
                        'updated' => $result['updated'],
                    ]);

                    if (! empty($result['failed_specialties'])) {
                        $body .= ' '.__('Spesialisasi gagal: :list', [
                            'list' => implode(', ', $result['failed_specialties']),
                        ]);
                    }

                    if (! empty($result['excluded_specialties'])) {
                        $body .= ' '.__('Spesialisasi dikecualikan: :list', [
                            'list' => implode(', ', $result['excluded_specialties']),
                        ]);
                    }

                    $notification = Notification::make()
                        ->title($status === 'success' ? __('Sinkronisasi Selesai') : __('Sinkronisasi :status', ['status' => $status]))
                        ->body($body);
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
