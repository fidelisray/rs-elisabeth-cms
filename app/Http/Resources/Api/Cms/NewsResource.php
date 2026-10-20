<?php

namespace App\Http\Resources\Api\Cms;

use App\Models\News;
use Illuminate\Http\Request;

/**
 * @mixin News
 */
class NewsResource extends BaseCmsResource
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
            'category' => $this->category,
            'author' => $this->author,
            'excerpt' => $this->shorts,
            'content' => $this->content,
            'image_path' => $this->image_path,
            'image_url' => $this->generateImageUrl($this->image_path),
            'is_published' => $this->is_published,
            'created_at' => $this->created_at,
        ];
    }
}
