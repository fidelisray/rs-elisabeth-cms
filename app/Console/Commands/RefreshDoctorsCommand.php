<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RefreshDoctorsCommand extends Command
{
    protected $signature = 'dokter:refresh';

    protected $description = 'Sinkronisasi dokter lalu warm-up cache dalam satu rangkaian terjadwal';

    public function handle(): int
    {
        if ($this->call('dokter:sync') !== self::SUCCESS) {
            $this->error('Sinkronisasi gagal; warm-up tidak dijalankan.');

            return self::FAILURE;
        }

        return $this->call('dokter:fetch-all');
    }
}
