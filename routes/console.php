<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Penarikan data dokter dari SIMRS MedInfras, setiap hari pukul 04:00 WIB.
// Satu lock mencakup sinkronisasi dan warm-up; warm-up hanya setelah sync sukses.
Schedule::command('dokter:refresh')
    ->when(fn () => config('rsapi.enabled', true))
    ->cron('0 4 * * *')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer();
