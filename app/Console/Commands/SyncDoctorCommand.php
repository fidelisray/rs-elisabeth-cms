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
    protected $description = 'Sinkronisasi data master dokter dari API internal RS ke database CMS';

    /**
     * Execute the console command.
     */
    public function handle(DoctorSyncService $syncService): int
    {
        $this->info('Memulai sinkronisasi data dokter dari API internal RS...');

        $result = $syncService->syncFromMedInfras();
        if (! empty($result['excluded_specialties'])) {
            $this->line('Spesialisasi dikecualikan: '.implode(', ', $result['excluded_specialties']));
        }

        $status = $result['status'] ?? ($result['total'] === 0 ? 'failed' : 'success');
        if ($status !== 'success') {
            $this->error("Sinkronisasi {$status}; {$result['total']} dokter valid diproses. Tidak dilaporkan sebagai sukses penuh.");
            if (! empty($result['failed_specialties'])) {
                $this->line('Spesialisasi gagal: '.implode(', ', $result['failed_specialties']));
            }

            return Command::FAILURE;
        }

        $this->info('Sinkronisasi selesai.');
        $this->table(
            ['Total Paramedis Unik', 'Baru Ditambahkan', 'Diperbarui', 'Tidak Berubah'],
            [[$result['total'], $result['created'], $result['updated'], $result['unchanged'] ?? 0]]
        );

        return Command::SUCCESS;
    }
}
