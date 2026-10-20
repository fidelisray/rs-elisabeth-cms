<?php

namespace App\Http\Resources\Api\Cms;

use App\Models\BannerPromotion;
use Illuminate\Http\Request;

/**
 * @mixin BannerPromotion
 */
class BannerPromotionResource extends BaseCmsResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'alternate_slugs' => $this->getAlternateSlugs(),
            'image_path' => $this->image_path,
            'image_url' => $this->generateImageUrl($this->image_path),
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
