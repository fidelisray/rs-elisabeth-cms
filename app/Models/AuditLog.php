<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Audit log append-only untuk kejadian keamanan penting.
 *
 * Tidak ada field password/password_hash. Metadata juga harus non-sensitif.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_user_id',
        'actor_type',
        'actor_email',
        'action',
        'subject_type',
        'subject_id',
        'subject_email',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public static function record(string $action, ?Model $subject = null, array $metadata = []): self
    {
        $actor = Auth::user();
        $subjectEmail = $subject?->getAttribute('email');

        return static::create([
            'actor_user_id' => $actor?->getKey(),
            'actor_type' => $actor ? 'user' : (app()->runningInConsole() ? 'console' : 'system'),
            'actor_email' => $actor?->getAttribute('email'),
            'action' => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'subject_email' => $subjectEmail,
            'metadata' => $metadata,
        ]);
    }
}
