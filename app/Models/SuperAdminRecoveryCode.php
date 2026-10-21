<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Menyimpan hash kode pemulihan sekali pakai untuk akun super_admin (K-5, K-24).
 *
 * Kode plaintext tidak pernah disimpan di database.
 */
class SuperAdminRecoveryCode extends Model
{
    protected $table = 'super_admin_recovery_codes';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'code_hash',
        'lookup_hash',
        'used_at',
        'created_at',
    ];

    protected $hidden = [
        'code_hash',
        'lookup_hash',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
