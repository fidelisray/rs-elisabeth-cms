<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * Penarikan data dokter dari SIMRS dijalankan setiap hari pukul 04:00 WIB.
 * Urutan wajib: dokter:sync (menulis ke tabel doctors) lalu dokter:fetch-all (warm-up cache).
 */
class DoctorSchedulerTest extends TestCase
{
    /**
     * @return array<int, Event>
     */
    private function doctorEvents(): array
    {
        $schedule = $this->app->make(Schedule::class);

        return collect($schedule->events())
            ->filter(fn (Event $event) => str_contains((string) $event->command, 'dokter:'))
            ->values()
            ->all();
    }

    public function test_sync_command_is_scheduled_daily_at_0400_jakarta(): void
    {
        $events = collect($this->doctorEvents())
            ->filter(fn (Event $event) => str_contains((string) $event->command, 'dokter:sync'));

        $this->assertCount(1, $events, 'dokter:sync harus terdaftar tepat satu kali di scheduler.');

        $event = $events->first();
        $this->assertSame('0 4 * * *', $event->expression, 'Jadwal harus setiap hari pukul 04:00.');
        $this->assertSame('Asia/Jakarta', $event->timezone, 'Jadwal harus memakai zona WIB.');
    }

    public function test_cache_warmup_runs_after_sync_on_the_same_schedule(): void
    {
        $events = collect($this->doctorEvents());

        $warmup = $events->first(fn (Event $event) => str_contains((string) $event->command, 'dokter:fetch-all'));
        $this->assertNotNull($warmup, 'dokter:fetch-all harus terdaftar di scheduler.');

        $this->assertSame('0 4 * * *', $warmup->expression);
        $this->assertSame('Asia/Jakarta', $warmup->timezone);
    }

    public function test_sync_does_not_overlap_with_itself(): void
    {
        $event = collect($this->doctorEvents())
            ->first(fn (Event $event) => str_contains((string) $event->command, 'dokter:sync'));

        $this->assertNotNull($event);
        $this->assertTrue($event->withoutOverlapping, 'Sinkronisasi tidak boleh berjalan dobel.');
    }
}
