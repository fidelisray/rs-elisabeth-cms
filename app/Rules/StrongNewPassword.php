<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

/**
 * Aturan password baru pada ganti password wajib (K-8).
 * - Minimal 8 karakter.
 * - Tidak boleh password default yang diketahui publik.
 * - Tidak boleh sama dengan password saat ini.
 */
class StrongNewPassword implements ValidationRule
{
    public const MIN_LENGTH = 8;

    /**
     * Password yang tertulis di kode/dokumentasi sehingga dianggap bocor.
     *
     * @var list<string>
     */
    public const FORBIDDEN = ['123456', 'admin@123456'];

    public function __construct(private readonly ?string $currentPasswordHash = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (string) $value;

        if (mb_strlen($value) < self::MIN_LENGTH) {
            $fail('Password baru minimal '.self::MIN_LENGTH.' karakter.');

            return;
        }

        if (in_array(strtolower($value), array_map('strtolower', self::FORBIDDEN), true)) {
            $fail('Password ini adalah password default dan tidak boleh dipakai. Pilih password lain.');

            return;
        }

        if ($this->currentPasswordHash !== null && Hash::check($value, $this->currentPasswordHash)) {
            $fail('Password baru tidak boleh sama dengan password saat ini.');
        }
    }
}
