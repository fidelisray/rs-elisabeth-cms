<?php

namespace App\Console\Commands;

use App\Services\DoctorSyncService;
use Illuminate\Console\Command;

class SyncDoctorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dokter:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi data master dokter dari SIMRS MedInfras ke database CMS';

    /**
     * Execute the console command.
     */
    public function handle(DoctorSyncService $syncService): int
    {
        $this->info('Memulai sinkronisasi data dokter dari SIMRS MedInfras...');

        $result = $syncService->syncFromMedInfras();

        $this->info('Sinkronisasi selesai.');
        $this->table(
            ['Total Paramedis Unik', 'Baru Ditambahkan', 'Diperbarui'],
            [[$result['total'], $result['created'], $result['updated']]]
        );

        return Command::SUCCESS;
    }
}
