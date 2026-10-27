<?php

namespace App\Console\Commands;

use App\Services\DoctorApiService;
use Illuminate\Console\Command;

class FetchAllDoctor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dokter:fetch-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch semua data dokter dari seluruh spesialisasi dan simpan ke cache';

    /**
     * Execute the console command.
     */
    public function handle(DoctorApiService $apiService): int
    {
        $this->info('Memulai warm-up cache master data (Spesialisasi & Unit)...');

        $spesialisasi = $apiService->getDaftarSpesialisasi();
        $this->line('Total Spesialisasi: '.count($spesialisasi));

        $units = $apiService->getDaftarUnits();
        $this->line('Total Units: '.count($units));

        $this->info('Memulai fetch data jadwal dokter...');
        $total = $apiService->fetchAndCacheAllDokter();
        $result = $apiService->getLastWarmupResult();
        $this->line('Spesialisasi dalam cakupan: '.($result['requested_specialties'] ?? 0));
        if (! empty($result['excluded_specialties'])) {
            $this->line('Spesialisasi dikecualikan: '.implode(', ', $result['excluded_specialties']));
        }
        if (! empty($result['failed_specialties'])) {
            $this->line('Spesialisasi gagal: '.implode(', ', $result['failed_specialties']));
        }

        if ($total === 0) {
            $this->error('Warm-up '.$result['status'].'. Snapshot baru tidak dipublikasikan.');
            $this->line(($result['cache_available'] ?? false)
                ? 'Cache lengkap sebelumnya dipertahankan.'
                : 'Cache agregat belum tersedia; tidak ada snapshot lama sebagai fallback.');

            return Command::FAILURE;
        }

        $this->info("Selesai. Total {$total} entri jadwal dokter berhasil di-cache.");

        return Command::SUCCESS;
    }
}
