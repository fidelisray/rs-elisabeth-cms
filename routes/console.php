<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Penarikan data dokter dari SIMRS MedInfras, setiap hari pukul 04:00 WIB.
// Urutan: sinkronisasi tabel doctors dulu, lalu warm-up cache.
Schedule::command('dokter:sync')
    ->cron('0 4 * * *')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('dokter:fetch-all')
    ->cron('0 4 * * *')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer();
