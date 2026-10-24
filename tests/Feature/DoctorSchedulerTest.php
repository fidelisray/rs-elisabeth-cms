<?php

namespace Tests\Feature;

use App\Services\DoctorSyncService;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DoctorSchedulerTest extends TestCase
{
    public function test_daily_refresh_is_due_at_0400_wib_and_not_before(): void
    {
        $events = collect($this->app->make(Schedule::class)->events());
        $event = $events->first(fn ($event) => str_contains((string) $event->command, 'dokter:refresh'));
        $this->assertNotNull($event);
        $this->assertSame('0 4 * * *', $event->expression);
        $this->assertSame('Asia/Jakarta', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        Carbon::setTestNow(Carbon::parse('2026-10-25 03:59:00', 'Asia/Jakarta'));
        try {
            $this->assertFalse($event->isDue($this->app));
            Carbon::setTestNow(Carbon::parse('2026-10-25 04:00:00', 'Asia/Jakarta'));
            $this->assertTrue($event->isDue($this->app));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_refresh_runs_sync_before_warmup(): void
    {
        $calls = new \ArrayObject;
        $this->registerProbe('dokter:sync', $calls);
        $this->registerProbe('dokter:fetch-all', $calls);
        $this->artisan('dokter:refresh')->assertSuccessful();
        $this->assertSame(['dokter:sync', 'dokter:fetch-all'], $calls->getArrayCopy());
    }

    public function test_failed_sync_stops_warmup(): void
    {
        $calls = new \ArrayObject;
        $this->registerProbe('dokter:sync', $calls, 1);
        $this->registerProbe('dokter:fetch-all', $calls);
        $this->artisan('dokter:refresh')->assertFailed();
        $this->assertSame(['dokter:sync'], $calls->getArrayCopy());
    }

    public function test_sync_reports_failure_when_no_doctors_can_be_fetched(): void
    {
        $this->mock(DoctorSyncService::class)->shouldReceive('syncFromMedInfras')->once()
            ->andReturn(['created' => 0, 'updated' => 0, 'total' => 0]);
        $this->artisan('dokter:sync')->assertFailed();
    }

    public function test_schedule_worker_can_start_and_remain_running(): void
    {
        // Berjalan sebelum batas menit; tidak menunggu atau memicu jadwal RS.
        $process = new Process([PHP_BINARY, 'artisan', 'schedule:work', '--no-interaction'], base_path(), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'CACHE_STORE' => 'array',
            'RS_API_BASE_URL' => 'http://127.0.0.1:1',
            'RS_MEDIN_API_CONSID' => '',
            'RS_MEDIN_API_SECRETKEY' => '',
            'WEB_FRONTEND_WEBHOOK_URL' => '',
        ]);
        try {
            $process->start();
            usleep(1500000);
            $this->assertTrue($process->isRunning(), $process->getErrorOutput());
        } finally {
            $process->stop(1);
        }
    }

    private function registerProbe(string $name, \ArrayObject $calls, int $exit = 0): void
    {
        $command = new class($name, $calls, $exit) extends Command
        {
            public function __construct(string $name, private \ArrayObject $calls, private int $exit)
            {
                $this->signature = $name;
                parent::__construct();
            }

            public function handle(): int
            {
                $this->calls[] = $this->getName();

                return $this->exit;
            }
        };
        $this->app->make(Kernel::class)->registerCommand($command);
    }
}
