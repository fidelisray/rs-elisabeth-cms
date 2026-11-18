<?php

namespace App\Http\Resources\Api\Cms;

use App\Models\DynamicForm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DynamicForm
 */
class DynamicFormSchemaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $availability = $this->evaluateAvailability();
        $version = $this->publishedVersion;
        $snapshot = is_array($version?->schema_snapshot) ? $version->schema_snapshot : null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'mode' => $this->mode->value,
            'locale' => $this->locale,
            'status' => $this->status->value,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'max_responses' => $this->max_responses,
            'allow_multiple_submissions' => $this->allow_multiple_submissions,
            'is_accepting_responses' => $availability['is_accepting_responses'],
            'remaining_quota' => $availability['remaining_quota'],
            'closed_reason' => $availability['closed_reason'],
            'closed_message' => $availability['is_accepting_responses']
                ? null
                : $this->defaultClosedMessage($availability['closed_reason']),
            'confirmation_message' => $this->defaultConfirmationMessage(),
            'published_version' => $version && $snapshot ? [
                'version_id' => $version->id,
                'version_number' => $version->version_number,
                'published_at' => $version->published_at?->toIso8601String(),
                'sections' => array_values((array) ($snapshot['sections'] ?? [])),
            ] : null,
        ];
    }
}
