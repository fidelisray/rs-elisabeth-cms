<?php

namespace App\Http\Resources\Api\Cms;

use App\Models\RoomFacility;
use Illuminate\Http\Request;

/**
 * @mixin RoomFacility
 */
class RoomFacilityResource extends BaseCmsResource
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
            'tagline' => $this->tagline,
            'description' => $this->description,
            'room_size' => $this->room_size,
            'bed_count' => $this->bed_count,
            'max_companion' => $this->max_companion,
            'image_path' => $this->image_path,
            'image_url' => $this->generateImageUrl($this->image_path),
            'amenities' => $this->amenities ?? [],
            'highlight_tags' => $this->highlight_tags ?? [],
            'sort_order' => $this->sort_order,
        ];
    }
}
