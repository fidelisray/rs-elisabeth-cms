<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\SuperAdminRecoveryCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
        SuperAdminRecoveryCode::where('user_id', $user->getKey())->delete();

        $codes = [];
        $seen = [];
        $now = now();

        while (count($codes) < self::CODE_COUNT) {
            $raw = $this->generateRawCode();

            if (isset($seen[$raw])) {
                continue;
            }

            $seen[$raw] = true;
            $formatted = implode('-', str_split($raw, 4));
            $codes[] = $formatted;

            SuperAdminRecoveryCode::create([
                'user_id' => $user->getKey(),
                'code_hash' => Hash::make($raw),
                'used_at' => null,
                'created_at' => $now,
            ]);
        }

        return $codes;
    }

    /**
     * Verifikasi dan konsumsi satu recovery code secara atomik.
     */
    public function verifyAndConsume(User $user, string $code): bool
    {
        $normalized = $this->normalize($code);

        if (strlen($normalized) !== self::CODE_LENGTH) {
            return false;
        }

        return DB::transaction(function () use ($user, $normalized): bool {
            $unusedCodes = SuperAdminRecoveryCode::where('user_id', $user->getKey())
                ->whereNull('used_at')
                ->lockForUpdate()
                ->get();

            $matched = null;

            foreach ($unusedCodes as $record) {
                if (Hash::check($normalized, $record->code_hash)) {
                    $matched = $record;

                    break;
                }
            }

            if (! $matched) {
                return false;
            }

            $matched->update(['used_at' => now()]);

            $user->forceFill([
                'must_change_password' => true,
            ])->save();

            AuditLog::record('super_admin.recovery_code_used', $user, [
                'recovery_code_id' => $matched->getKey(),
                'ip' => request()?->ip(),
            ]);

            return true;
        });
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
