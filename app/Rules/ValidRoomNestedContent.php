<?php

namespace App\Rules;

use App\Support\RoomNestedContent;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidRoomNestedContent implements ValidationRule
{
    public function __construct(private readonly string $field) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $validator = RoomNestedContent::validator(RoomNestedContent::prepare([$this->field => $value]));

        if ($validator->fails()) {
            $fail('Lengkapi struktur dan teks Indonesia serta English pada setiap grup/item. Spesifikasi opsional harus diisi dalam kedua bahasa bila digunakan.');
        }
    }
}
