<?php

namespace App\Models;

use App\Enums\DynamicFormMode;
use App\Enums\DynamicFormStatus;
use App\Models\Concerns\GuardsHardDelete;
use App\Observers\WebFrontendWebhookObserver;
use App\Support\ApiCacheInvalidator;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

#[ObservedBy([WebFrontendWebhookObserver::class])]
class DynamicForm extends Model
{
    use GuardsHardDelete {
        GuardsHardDelete::forceDelete insteadof SoftDeletes;
        GuardsHardDelete::forceDeleteQuietly insteadof SoftDeletes;
    }
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'mode',
        'locale',
        'status',
        'published_version_id',
        'starts_at',
        'ends_at',
        'max_responses',
        'allow_multiple_submissions',
        'is_public_listed',
        'ticket_prefix',
        'confirmation_message',
        'closed_message',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'mode' => DynamicFormMode::class,
            'status' => DynamicFormStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_responses' => 'integer',
            'allow_multiple_submissions' => 'boolean',
            'is_public_listed' => 'boolean',
        ];
    }

    public function getWebhookModuleName(): string
    {
        return 'dynamic_forms';
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DynamicFormVersion::class, 'dynamic_form_id')
            ->orderByDesc('version_number');
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(DynamicFormVersion::class, 'published_version_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(DynamicFormSubmission::class, 'dynamic_form_id');
    }

    public function submissionAnswers(): HasMany
    {
        return $this->hasMany(DynamicFormSubmissionAnswer::class, 'dynamic_form_id');
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(DynamicFormUpload::class, 'dynamic_form_id');
    }

    public function latestDraftVersion(): ?DynamicFormVersion
    {
        return $this->versions()
            ->where('status', 'draft')
            ->orderByDesc('version_number')
            ->first();
    }

    public function latestVersion(): ?DynamicFormVersion
    {
        return $this->versions()
            ->orderByDesc('version_number')
            ->first();
    }

    /**
     * Hitung status ketersediaan form saat ini secara deterministik.
     *
     * @return array{is_accepting_responses: bool, closed_reason: ?string, remaining_quota: ?int, submitted_count: int}
     */
    public function evaluateAvailability(?int $submittedCount = null): array
    {
        $count = $submittedCount ?? $this->submissions()->count();
        $remainingQuota = $this->max_responses !== null
            ? max(0, $this->max_responses - $count)
            : null;

        if ($this->status !== DynamicFormStatus::Published || ! $this->published_version_id) {
            return [
                'is_accepting_responses' => false,
                'closed_reason' => $this->status === DynamicFormStatus::Closed ? 'closed' : 'not_published',
                'remaining_quota' => $remainingQuota,
                'submitted_count' => $count,
            ];
        }

        $now = now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return [
                'is_accepting_responses' => false,
                'closed_reason' => 'not_started',
                'remaining_quota' => $remainingQuota,
                'submitted_count' => $count,
            ];
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return [
                'is_accepting_responses' => false,
                'closed_reason' => 'ended',
                'remaining_quota' => $remainingQuota,
                'submitted_count' => $count,
            ];
        }

        if ($this->max_responses !== null && $count >= $this->max_responses) {
            return [
                'is_accepting_responses' => false,
                'closed_reason' => 'quota_full',
                'remaining_quota' => 0,
                'submitted_count' => $count,
            ];
        }

        return [
            'is_accepting_responses' => true,
            'closed_reason' => null,
            'remaining_quota' => $remainingQuota,
            'submitted_count' => $count,
        ];
    }

    public function defaultClosedMessage(?string $reason = null): string
    {
        if (filled($this->closed_message)) {
            return (string) $this->closed_message;
        }

        return match ($reason) {
            'not_started' => $this->locale === 'en'
                ? 'This form is not open for responses yet.'
                : 'Formulir ini belum dibuka untuk pengisian.',
            'ended' => $this->locale === 'en'
                ? 'The submission period for this form has ended.'
                : 'Periode pengisian formulir ini telah berakhir.',
            'quota_full' => $this->locale === 'en'
                ? 'The maximum response quota for this form has been reached.'
                : 'Kuota pengisian formulir ini sudah penuh.',
            default => $this->locale === 'en'
                ? 'This form is currently closed.'
                : 'Formulir ini sedang ditutup.',
        };
    }

    public function defaultConfirmationMessage(): string
    {
        if (filled($this->confirmation_message)) {
            return (string) $this->confirmation_message;
        }

        if ($this->mode === DynamicFormMode::Feedback) {
            return $this->locale === 'en'
                ? 'Thank you. Your feedback has been recorded.'
                : 'Terima kasih. Masukan Anda telah dicatat dan akan ditindaklanjuti.';
        }

        return $this->locale === 'en'
            ? 'Thank you. Your response has been recorded.'
            : 'Terima kasih. Jawaban Anda telah berhasil dikirim.';
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            $model->created_by ??= Auth::user()?->email ?? 'system';
        });

        static::updating(function (self $model): void {
            $model->updated_by = Auth::user()?->email ?? 'system';
        });

        static::deleting(function (self $model): void {
            if (! $model->isForceDeleting()) {
                $model->deleted_by = Auth::user()?->email ?? 'system';
                $model->saveQuietly();
            }
        });

        static::saved(function (): void {
            ApiCacheInvalidator::forgetForModule('dynamic_forms');
        });

        static::deleted(function (): void {
            ApiCacheInvalidator::forgetForModule('dynamic_forms');
        });
    }
}
