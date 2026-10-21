<?php

namespace Tests\Feature\Auth;

use App\Filament\Pages\Auth\RecoveryLogin;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\SuperAdminRecoveryCode;
use App\Models\User;
use App\Services\RecoveryCodeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecoveryVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create(['email' => 'admin@example.test', 'must_change_password' => false]);
        $user->assignRole('super_admin');

        return $user;
    }

    public static function verificationCases(): array
    {
        $cases = [
            'valid-first', 'valid-last', 'unknown-user', 'wrong-code', 'malformed',
            'used-code', 'no-codes', 'staff', 'inactive',
        ];

        return array_combine($cases, array_map(fn ($case) => [$case], $cases));
    }

    #[DataProvider('verificationCases')]
    public function test_each_service_attempt_performs_one_matching_cost_hash_check(string $case): void
    {
        $user = $this->admin();
        $service = new RecoveryCodeService;
        $codes = $service->generateFor($user);
        $code = $codes[0];

        switch ($case) {
            case 'valid-last': $code = $codes[9];
                break;
            case 'unknown-user': $user = null;
                break;
            case 'wrong-code': $code = 'ZZZZ-ZZZZ-ZZZZ-ZZZZ';
                break;
            case 'malformed': $code = 'short';
                break;
            case 'used-code': $service->verifyAndConsume($user, $code);
                $user->refresh();
                break;
            case 'no-codes': SuperAdminRecoveryCode::query()->delete();
                break;
            case 'staff': $user = User::factory()->create();
                break;
            case 'inactive': DB::table('users')->where('id', $user->getKey())->update(['is_active' => false]);
                $user->refresh();
                break;
        }

        $hasher = Hash::getFacadeRoot();
        $checks = [];
        $proxy = \Mockery::mock($hasher)->makePartial();
        Hash::swap($proxy);
        $proxy->shouldReceive('check')->andReturnUsing(function ($plain, $hash, $options = []) use ($hasher, &$checks): bool {
            $checks[] = $hasher->info($hash);

            return $hasher->check($plain, $hash, $options);
        });

        $verified = $service->verifyAndConsume($user, $code);
        $this->assertSame(in_array($case, ['valid-first', 'valid-last'], true), $verified);
        $this->assertCount(1, $checks);
        $this->assertSame((int) config('hashing.bcrypt.rounds'), $checks[0]['options']['cost']);
    }

    public function test_generation_stores_full_indexed_lookup_hashes_but_hides_all_code_hashes(): void
    {
        $user = $this->admin();
        $service = new RecoveryCodeService;
        $codes = $service->generateFor($user);
        $rows = SuperAdminRecoveryCode::where('user_id', $user->id)->orderBy('id')->get();

        foreach ($codes as $index => $code) {
            $row = $rows[$index];
            $this->assertSame(hash('sha256', $service->normalize($code)), $row->lookup_hash);
            $this->assertSame(64, strlen($row->lookup_hash));
            $this->assertArrayNotHasKey('lookup_hash', $row->toArray());
            $this->assertArrayNotHasKey('code_hash', $row->toArray());
        }

        $this->expectException(UniqueConstraintViolationException::class);
        DB::transaction(fn () => DB::table('super_admin_recovery_codes')->insert([
            'user_id' => $user->id, 'lookup_hash' => $rows[0]->lookup_hash,
            'code_hash' => $rows[0]->code_hash,
        ]));
    }

    public function test_bcrypt_is_still_verified_after_a_lookup_match(): void
    {
        $user = $this->admin();
        $service = new RecoveryCodeService;
        $code = $service->generateFor($user)[0];
        DB::table('super_admin_recovery_codes')->where('lookup_hash', hash('sha256', $service->normalize($code)))
            ->update(['code_hash' => Hash::make('not-the-original-code')]);

        $this->assertFalse($service->verifyAndConsume($user, $code));
        $this->assertSame(10, $service->remainingCount($user));
        $this->assertSame(0, AuditLog::where('action', 'super_admin.recovery_code_used')->count());
    }

    public function test_regeneration_is_atomic_when_inserting_a_replacement_set_fails(): void
    {
        $user = $this->admin();
        $service = new RecoveryCodeService;
        $codes = $service->generateFor($user);
        $dispatcher = SuperAdminRecoveryCode::getEventDispatcher();
        SuperAdminRecoveryCode::setEventDispatcher(clone $dispatcher);

        try {
            SuperAdminRecoveryCode::creating(fn () => throw new \RuntimeException('Simulated replacement failure.'));
            try {
                $service->generateFor($user);
                $this->fail('Expected replacement failure.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('Simulated replacement failure.', $exception->getMessage());
            }
        } finally {
            SuperAdminRecoveryCode::setEventDispatcher($dispatcher);
        }

        $this->assertSame(10, $service->remainingCount($user));
        $this->assertTrue($service->verifyAndConsume($user, $codes[0]));
    }

    public function test_consumption_failure_rolls_back_used_flag_and_password_change_requirement(): void
    {
        $user = $this->admin();
        $service = new RecoveryCodeService;
        $code = $service->generateFor($user)[0];
        $dispatcher = AuditLog::getEventDispatcher();
        AuditLog::setEventDispatcher(clone $dispatcher);

        try {
            AuditLog::creating(fn () => throw new \RuntimeException('Simulated audit failure.'));
            try {
                $service->verifyAndConsume($user, $code);
                $this->fail('Expected audit failure.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('Simulated audit failure.', $exception->getMessage());
            }
        } finally {
            AuditLog::setEventDispatcher($dispatcher);
        }

        $this->assertSame(10, $service->remainingCount($user));
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertTrue($service->verifyAndConsume($user, $code));
    }

    public function test_cli_init_normalizes_email_and_mixed_case_recovery_logs_in(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(0, Artisan::call('app:super-admin:init', ['--email' => '  Admin@Example.TEST  ']));
        preg_match('/RECOVERY_CODE_1=([A-Z2-9-]+)/', Artisan::output(), $matches);
        $user = User::where('email', 'admin@example.test')->firstOrFail();

        Livewire::test(RecoveryLogin::class)->fillForm(['email' => '  ADMIN@EXAMPLE.test  ', 'recovery_code' => strtolower($matches[1])])
            ->call('authenticate')->assertHasNoFormErrors()
            ->assertRedirect(route('filament.admin.pages.force-password-change'));

        $this->assertSame($user->id, Auth::id());
        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_model_normalizes_emails_and_enforces_uniqueness_on_normalized_values(): void
    {
        $user = User::factory()->create(['email' => '  Mixed@Example.TEST  ']);
        $this->assertSame('mixed@example.test', $user->fresh()->email);
        $user->update(['email' => '  Changed@Example.TEST  ']);
        $this->assertSame('changed@example.test', $user->fresh()->email);
        $this->expectException(UniqueConstraintViolationException::class);
        DB::transaction(fn () => User::factory()->create(['email' => 'CHANGED@example.test']));
    }

    public function test_user_form_reports_duplicate_normalized_email_as_validation_error(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->admin();
        $this->actingAs($user);
        $marketing = Role::findByName('marketing', 'web');

        Livewire::test(CreateUser::class)->fillForm([
            'name' => 'Test Staff', 'email' => ' MARKETING@RS-ELISABETH.COM ',
            'roles' => $marketing->id, 'is_active' => true,
        ])->call('create')->assertHasFormErrors(['email']);
    }

    public function test_mixed_case_attempts_share_the_same_recovery_rate_limit(): void
    {
        $user = $this->admin();
        $service = new RecoveryCodeService;
        $code = $service->generateFor($user)[0];
        $key = 'recovery-login:'.$user->email.'|127.0.0.1';
        RateLimiter::clear($key);

        foreach (range(1, 5) as $n) {
            Livewire::test(RecoveryLogin::class)->fillForm([
                'email' => $n % 2 ? strtoupper($user->email) : $user->email, 'recovery_code' => 'ZZZZ-ZZZZ-ZZZZ-ZZZZ',
            ])->call('authenticate')->assertHasFormErrors(['email']);
        }

        $form = Livewire::test(RecoveryLogin::class)->fillForm(['email' => $user->email, 'recovery_code' => $code])
            ->call('authenticate')->assertHasFormErrors(['email']);
        $this->assertStringContainsString('Terlalu banyak', $form->instance()->getErrorBag()->first('data.email'));
        $this->assertSame(10, $service->remainingCount($user));
    }

    public function test_production_bcrypt_cost_is_used_for_unknown_and_wrong_credentials(): void
    {
        config(['hashing.bcrypt.rounds' => 12]);
        Hash::driver()->setRounds(12);
        $user = $this->admin();
        $service = new RecoveryCodeService;
        $codes = $service->generateFor($user);
        $hasher = Hash::getFacadeRoot();
        $checks = [];
        $proxy = \Mockery::mock($hasher)->makePartial();
        Hash::swap($proxy);
        $proxy->shouldReceive('check')->andReturnUsing(function ($plain, $hash, $options = []) use ($hasher, &$checks): bool {
            $checks[] = $hasher->info($hash)['options']['cost'];

            return $hasher->check($plain, $hash, $options);
        });

        $this->assertFalse($service->verifyAndConsume(null, $codes[0]));
        $this->assertFalse($service->verifyAndConsume($user, 'ZZZZ-ZZZZ-ZZZZ-ZZZZ'));
        $this->assertTrue($service->verifyAndConsume($user, $codes[9]));
        $this->assertSame([12, 12, 12], $checks);
    }

    public function test_code_consumed_between_hash_check_and_transaction_is_not_consumed_twice(): void
    {
        $user = $this->admin();
        $service = new RecoveryCodeService;
        $code = $service->generateFor($user)[0];
        $hasher = Hash::getFacadeRoot();
        $proxy = \Mockery::mock($hasher)->makePartial();
        Hash::swap($proxy);
        $interleaved = false;
        $proxy->shouldReceive('check')->andReturnUsing(function ($plain, $hash, $options = []) use ($hasher, $user, $service, $code, &$interleaved): bool {
            $verified = $hasher->check($plain, $hash, $options);
            if ($verified && ! $interleaved) {
                $interleaved = true;
                $this->assertTrue($service->verifyAndConsume($user, $code));
            }

            return $verified;
        });

        $this->assertFalse($service->verifyAndConsume($user, $code));
        $this->assertTrue($interleaved);
        $this->assertSame(9, $service->remainingCount($user));
        $this->assertSame(1, AuditLog::where('action', 'super_admin.recovery_code_used')->count());
    }

    public function test_regeneration_between_hash_check_and_consumption_invalidates_the_candidate(): void
    {
        $user = $this->admin();
        $service = new RecoveryCodeService;
        $code = $service->generateFor($user)[0];
        $hasher = Hash::getFacadeRoot();
        $proxy = \Mockery::mock($hasher)->makePartial();
        Hash::swap($proxy);
        $regenerated = false;
        $proxy->shouldReceive('check')->andReturnUsing(function ($plain, $hash, $options = []) use ($hasher, $user, $service, &$regenerated): bool {
            $verified = $hasher->check($plain, $hash, $options);
            if ($verified && ! $regenerated) {
                $regenerated = true;
                $service->generateFor($user);
            }

            return $verified;
        });

        $this->assertFalse($service->verifyAndConsume($user, $code));
        $this->assertTrue($regenerated);
        $this->assertSame(10, $service->remainingCount($user));
        $this->assertSame(0, AuditLog::where('action', 'super_admin.recovery_code_used')->count());
    }

    public function test_recovery_audit_does_not_contain_lookup_or_verifier_hashes(): void
    {
        $user = $this->admin();
        $service = new RecoveryCodeService;
        $code = $service->generateFor($user)[0];
        $row = SuperAdminRecoveryCode::where('lookup_hash', hash('sha256', $service->normalize($code)))->firstOrFail();
        $this->assertTrue($service->verifyAndConsume($user, $code));
        $audit = AuditLog::where('action', 'super_admin.recovery_code_used')->sole()->toJson();

        foreach ([$code, $service->normalize($code), $row->code_hash, $row->lookup_hash] as $secret) {
            $this->assertStringNotContainsString($secret, $audit);
        }
    }
}
