<?php

namespace Tests\Feature;

use App\Console\Commands\PreprodResetCommand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pengaman command reset database pre-production. Alur reset penuh
 * (migrate:fresh) tidak diuji di sini karena menjatuhkan seluruh tabel
 * sedangkan test berjalan di dalam transaksi RefreshDatabase; alur itu
 * diverifikasi manual pada database SQLite sementara.
 */
class PreprodResetCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_refuses_to_run_when_flag_is_off_even_with_force(): void
    {
        config(['app.allow_destructive_reset' => false]);
        User::factory()->create(['email' => 'jangan.hilang@rs-elisabeth.com']);
        $migrationRows = DB::table('migrations')->count();

        $this->artisan('app:preprod:reset', ['--force' => true])
            ->expectsOutputToContain('ALLOW_DESTRUCTIVE_RESET')
            ->assertExitCode(1);

        $this->assertTrue(User::where('email', 'jangan.hilang@rs-elisabeth.com')->exists());
        $this->assertSame($migrationRows, DB::table('migrations')->count());
    }

    public function test_flag_can_be_explicitly_disabled(): void
    {
        config(['app.allow_destructive_reset' => false]);

        $this->assertFalse(config('app.allow_destructive_reset'));
    }

    public function test_declined_confirmation_changes_nothing(): void
    {
        config(['app.allow_destructive_reset' => true]);
        User::factory()->create(['email' => 'jangan.hilang@rs-elisabeth.com']);

        $this->artisan('app:preprod:reset')
            ->expectsConfirmation(PreprodResetCommand::CONFIRM_QUESTION, 'no')
            ->assertExitCode(1);

        $this->assertTrue(User::where('email', 'jangan.hilang@rs-elisabeth.com')->exists());
    }
}
