<?php

namespace App\Http\Resources\Api\Cms;

use App\Models\DynamicForm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DynamicForm
 */
class DynamicFormListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $availability = $this->evaluateAvailability();

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
            'remaining_quota' => $availability['remaining_quota'],
            'is_accepting_responses' => $availability['is_accepting_responses'],
            'allow_multiple_submissions' => $this->allow_multiple_submissions,
            'is_public_listed' => (bool) $this->is_public_listed,
            'published_version_number' => $this->publishedVersion?->version_number,
        ];
    }
}
