<?php

namespace App\Http\Resources\Api\Cms;

use App\Models\FacilityService;
use Illuminate\Http\Request;

/**
 * @mixin FacilityService
 */
class FacilityServiceResource extends BaseCmsResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'alternate_slugs' => $this->getAlternateSlugs(),
            'category' => $this->category,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'image_path' => $this->image_path,
            'image_url' => $this->generateImageUrl($this->image_path),
            'highlights' => $this->highlights,
            'wa_link_text' => $this->wa_link_text,
            'wa_number' => $this->wa_number,
            'wa_prefilled_message' => $this->wa_prefilled_message,
            'wa_action_url' => $this->wa_number ? "https://wa.me/{$this->wa_number}?text=".urlencode((string) $this->wa_prefilled_message) : null,
            'has_appointment_cta' => $this->has_appointment_cta,
        ];
    }
}
