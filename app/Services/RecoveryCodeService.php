<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\SuperAdminRecoveryCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Layanan pembuatan dan verifikasi kode pemulihan sekali pakai untuk super_admin (MVP-1.5).
 */
class RecoveryCodeService
{
    public const CODE_COUNT = 10;

    public const CODE_LENGTH = 16;

    /**
     * Alfabet tanpa karakter ambigu (tanpa 0, O, 1, I, l).
     */
    public const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    // Non-secret dummy verifier, never assigned to an account or usable as a recovery code.
    private const DUMMY_BCRYPT_HASH = '$2y$12$iHjUsib.uQwHAfZpxp2gWu232k1iP4Mzvq45pfgNnOtRFpkzA04Q6';

    private static array $dummyHashes = [];

    /**
     * Normalisasi input kode: huruf kapital, hapus spasi dan tanda hubung.
     */
    public function normalize(string $code): string
    {
        return strtoupper((string) preg_replace('/[\s-]+/', '', $code));
    }

    /**
     * Buat 10 recovery code baru untuk user (menghapus kode lama bila ada).
     * Mengembalikan daftar kode berformat XXXX-XXXX-XXXX-XXXX untuk ditampilkan satu kali.
     *
     * @return list<string>
     */
    public function generateFor(User $user): array
    {
        return DB::transaction(function () use ($user): array {
            $lockedUser = User::whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if (! $lockedUser->is_active || ! $lockedUser->isSuperAdmin()) {
                throw new RuntimeException('Recovery codes require an active super_admin.');
            }

            SuperAdminRecoveryCode::where('user_id', $lockedUser->getKey())->delete();
            $codes = [];
            $seen = [];

            while (count($codes) < self::CODE_COUNT) {
                $raw = $this->generateRawCode();
                if (isset($seen[$raw])) {
                    continue;
                }

                $seen[$raw] = true;
                $codes[] = implode('-', str_split($raw, 4));
                SuperAdminRecoveryCode::create([
                    'user_id' => $lockedUser->getKey(),
                    'code_hash' => Hash::make($raw),
                    // Codes have high random entropy; use the full digest, not a collision-prone prefix.
                    'lookup_hash' => hash('sha256', $raw),
                    'used_at' => null,
                    'created_at' => now(),
                ]);
            }

            return $codes;
        });
    }

    /**
     * Verifikasi dan konsumsi satu recovery code secara atomik.
     */
    public function verifyAndConsume(?User $user, string $code): bool
    {
        $dummyHash = $this->dummyHash();
        $normalized = $this->normalize($code);
        $validFormat = strlen($normalized) === self::CODE_LENGTH && strspn($normalized, self::ALPHABET) === self::CODE_LENGTH;
        $eligible = $user && $user->is_active && $user->isSuperAdmin();
        $input = $validFormat ? $normalized : 'invalid-recovery-input';
        $lookup = hash('sha256', $input);

        // All submitted credential paths perform one lookup and exactly one expensive check.
        $candidate = SuperAdminRecoveryCode::where('user_id', $eligible ? $user->getKey() : 0)
            ->where('lookup_hash', $lookup)->whereNull('used_at')->first();
        $verified = Hash::check($input, $candidate?->code_hash ?? $dummyHash);

        if (! $verified || ! $validFormat || ! $eligible || ! $candidate) {
            return false;
        }

        return DB::transaction(function () use ($user, $candidate, $lookup): bool {
            // Generation and consumption lock the same user row in the same order.
            $lockedUser = User::whereKey($user->getKey())->lockForUpdate()->first();
            if (! $lockedUser || ! $lockedUser->is_active || ! $lockedUser->isSuperAdmin()) {
                return false;
            }

            $matched = SuperAdminRecoveryCode::whereKey($candidate->getKey())
                ->where('user_id', $lockedUser->getKey())->where('lookup_hash', $lookup)
                ->whereNull('used_at')->lockForUpdate()->first();

            if (! $matched || ! hash_equals($candidate->code_hash, $matched->code_hash)) {
                return false;
            }

            $matched->update(['used_at' => now()]);

            $lockedUser->forceFill([
                'must_change_password' => true,
            ])->save();

            AuditLog::record('super_admin.recovery_code_used', $lockedUser, [
                'recovery_code_id' => $matched->getKey(),
                'ip' => request()?->ip(),
            ]);

            return true;
        });
    }

    private function dummyHash(): string
    {
        $configuration = json_encode([Hash::getDefaultDriver(), config('hashing.bcrypt'), config('hashing.argon')], JSON_THROW_ON_ERROR);

        return self::$dummyHashes[$configuration] ??= Hash::needsRehash(self::DUMMY_BCRYPT_HASH)
            ? Hash::make('non-secret-dummy-recovery-verifier')
            : self::DUMMY_BCRYPT_HASH;
    }

    /**
     * Hitung jumlah recovery code yang belum terpakai.
     */
    public function remainingCount(User $user): int
    {
        return SuperAdminRecoveryCode::where('user_id', $user->getKey())
            ->whereNull('used_at')
            ->count();
    }

    private function generateRawCode(): string
    {
        $alphabetLength = strlen(self::ALPHABET);
        $raw = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $raw .= self::ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $raw;
    }
}
