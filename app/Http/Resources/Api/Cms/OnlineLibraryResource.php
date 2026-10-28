<?php

namespace App\Http\Resources\Api\Cms;

use App\Models\OnlineLibrary;
use Illuminate\Http\Request;

/**
 * @mixin OnlineLibrary
 */
class OnlineLibraryResource extends BaseCmsResource
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
            'description' => $this->description,
            'file_path' => $this->file_path,
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'file_url' => $this->generateImageUrl($this->file_path),
            'is_published' => $this->is_published,
            'created_at' => $this->created_at,
        ];
    }
}
