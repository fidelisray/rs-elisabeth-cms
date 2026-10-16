<?php

namespace App\Http\Resources\Api\Cms;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

abstract class BaseCmsResource extends JsonResource
{
    /**
     * Generate a full public URL from a storage path.
     * Returns null if path is empty or null.
     */
    protected function generateImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(config('filesystems.media_disk', 's3'));

        return $disk->url($path);
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Data retrieved successfully.',
        ];
    }
}
