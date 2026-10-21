<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\SuperAdminRecoveryCode;
use App\Models\User;
use App\Services\RecoveryCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * MVP-1.5: unit test untuk pembuatan, penyimpanan hash, dan konsumsi recovery code.
 */
class RecoveryCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    private RecoveryCodeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new RecoveryCodeService;
    }

    private function createSuperAdmin(): User
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create(['must_change_password' => false]);
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_generates_ten_unique_formatted_codes_from_unambiguous_alphabet(): void
    {
        $user = $this->createSuperAdmin();

        $codes = $this->service->generateFor($user);

        $this->assertCount(10, $codes);
        $this->assertCount(10, array_unique($codes));

        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{4}(-[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{4}){3}$/', $code);
        }
    }

    public function test_stores_only_hashed_codes_in_database_never_plaintext(): void
    {
        $user = $this->createSuperAdmin();

        $codes = $this->service->generateFor($user);
        $records = SuperAdminRecoveryCode::where('user_id', $user->getKey())->orderBy('id')->get();

        $this->assertCount(10, $records);

        foreach ($codes as $index => $code) {
            $normalized = $this->service->normalize($code);
            $hash = $records[$index]->code_hash;

            $this->assertNotSame($code, $hash);
            $this->assertNotSame($normalized, $hash);
            $this->assertTrue(Hash::check($normalized, $hash));
            $this->assertNull($records[$index]->used_at);
        }
    }

    public function test_verifies_and_consumes_valid_code_once_with_flexible_formatting(): void
    {
        $user = $this->createSuperAdmin();
        $codes = $this->service->generateFor($user);

        $this->assertSame(10, $this->service->remainingCount($user));

        // Masukkan dengan huruf kecil dan spasi untuk menguji normalisasi.
        $input = strtolower(str_replace('-', ' ', $codes[0]));

        $this->assertTrue($this->service->verifyAndConsume($user, $input));
        $this->assertSame(9, $this->service->remainingCount($user));
        $this->assertTrue($user->fresh()->must_change_password);

        // Pemakaian kedua pada kode yang sama wajib ditolak.
        $this->assertFalse($this->service->verifyAndConsume($user, $codes[0]));
        $this->assertSame(9, $this->service->remainingCount($user));

        $log = AuditLog::where('action', 'super_admin.recovery_code_used')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString($codes[0], (string) json_encode($log->toArray()));
        $this->assertStringNotContainsString($this->service->normalize($codes[0]), (string) json_encode($log->toArray()));
    }

    public function test_rejects_wrong_code_without_consuming_any_code(): void
    {
        $user = $this->createSuperAdmin();
        $this->service->generateFor($user);

        $this->assertFalse($this->service->verifyAndConsume($user, '2345-6789-ABCD-EFGH'));
        $this->assertSame(10, $this->service->remainingCount($user));
        $this->assertFalse($user->fresh()->must_change_password);
    }
}
