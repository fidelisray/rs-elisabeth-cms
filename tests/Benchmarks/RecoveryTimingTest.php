<?php

namespace Tests\Benchmarks;

use App\Filament\Pages\Auth\RecoveryLogin;
use App\Models\Role;
use App\Models\User;
use App\Services\RecoveryCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/** Run explicitly; latency observations are not a deterministic CI timing guarantee. */
class RecoveryTimingTest extends TestCase
{
    use RefreshDatabase;

    public function test_observes_failed_recovery_latency_with_production_hashing_parameters(): void
    {
        config(['hashing.bcrypt.rounds' => 12]);
        Hash::driver()->setRounds(12);
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create(['email' => 'benchmark@example.test', 'must_change_password' => false]);
        $user->assignRole('super_admin');
        app(RecoveryCodeService::class)->generateFor($user);
        $samples = ['unknown' => [], 'wrong-code' => []];

        // Alternate cases and ignore a warm-up pair; clear only this test's rate limiter keys.
        foreach (range(0, 5) as $iteration) {
            foreach (['unknown' => 'missing@example.test', 'wrong-code' => $user->email] as $case => $email) {
                RateLimiter::clear('recovery-login:'.$email.'|127.0.0.1');
                $start = hrtime(true);
                $component = Livewire::test(RecoveryLogin::class)->fillForm([
                    'email' => $email, 'recovery_code' => 'ZZZZ-ZZZZ-ZZZZ-ZZZZ',
                ])->call('authenticate');
                $elapsed = (hrtime(true) - $start) / 1_000_000;
                $component->assertHasFormErrors(['email']);
                $this->assertSame(RecoveryLogin::INVALID_CREDENTIALS_MESSAGE, $component->instance()->getErrorBag()->first('data.email'));
                $this->assertFalse(Auth::check());
                if ($iteration > 0) {
                    $samples[$case][] = round($elapsed, 2);
                }
            }
        }

        $medians = [];
        foreach ($samples as $case => $measurements) {
            sort($measurements);
            $medians[$case] = $measurements[2];
        }

        fwrite(STDOUT, PHP_EOL.'RECOVERY_TIMING_OBSERVATIONS='.json_encode([
            'bcrypt_cost' => 12, 'samples_ms' => $samples, 'median_ms' => $medians,
            'median_delta_ms' => round(abs($medians['unknown'] - $medians['wrong-code']), 2),
        ], JSON_THROW_ON_ERROR).PHP_EOL);
    }
}
