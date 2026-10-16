<?php

namespace App\Http\Resources\Api\Cms;

use App\Models\Article;
use Illuminate\Http\Request;

/**
 * @mixin Article
 */
class ArticleResource extends BaseCmsResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->judul,
            'slug' => $this->slug,
            'image_path' => $this->image_path,
            'image_url' => $this->generateImageUrl($this->image_path),
            'excerpt' => $this->shorts,
            'content' => $this->content,
            'tags' => $this->tags,
            'author' => $this->author,
            'views' => $this->views,
            'created_at' => $this->created_at,
        ];
    }
}
