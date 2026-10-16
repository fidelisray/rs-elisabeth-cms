<?php

namespace App\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait FormatsArticleData
{
    /**
     * Format raw API data for articles/news into a standardized collection.
     * Extracts images from HTML, creates slugs, and standardizes fields.
     */
    protected function formatApiData(array $apiData): Collection
    {
        return collect($apiData)->map(function ($item) {

            /*
            // ----- MASTER API LAMA (JANGAN DIHAPUS, UNCOMMENT JIKA INGIN KEMBALI) -----
            // Extract the first image source from the HTML description (if available)
            preg_match('/<img.+src=[\'"](?P<src>.+?)[\'"].*>/i', $item['deskripsi'] ?? '', $image);
            $thumbnail = $image['src'] ?? asset('images/hero.jpg');

            return [
                'id' => $item['id'] ?? 0,
                'title' => $item['judul'] ?? 'Tanpa Judul',
                'slug' => Str::slug($item['judul'] ?? 'item-' . ($item['id'] ?? rand())),
                'image' => $thumbnail,
                'date' => $item['created_at'] ?? now()->toDateString(),
                'excerpt' => $item['subjudul'] ?? '',
                'content' => $item['deskripsi'] ?? '',
            ];
            */

            // ----- NEW CMS API & FALLBACK LOGIC -----
            // (Menggunakan asset() agar dinamis terhadap host seperti RoomFacilities)
            if (! empty($item['image_path'])) {
                $thumbnail = asset('storage/'.$item['image_path']);
            } elseif (! empty($item['image_url'])) {
                $thumbnail = $item['image_url'];
            } else {
                // Legacy RS API logic
                preg_match('/<img.+src=[\'"](?P<src>.+?)[\'"].*>/i', $item['deskripsi'] ?? $item['content'] ?? '', $image);
                $thumbnail = $image['src'] ?? asset('images/hero.jpg');
            }

            return [
                'id' => $item['id'] ?? 0,
                'title' => $item['judul'] ?? $item['title'] ?? 'Tanpa Judul',
                'slug' => $item['slug'] ?? Str::slug($item['judul'] ?? $item['title'] ?? 'item-'.($item['id'] ?? rand())),
                'image' => $thumbnail,
                'date' => $item['created_at'] ?? now()->toDateString(),
                'category' => $this->getCategoryLabel($item['category'] ?? null),
                'category_slug' => $item['category'] ?? null,
                'excerpt' => $item['shorts'] ?? $item['subjudul'] ?? Str::limit(strip_tags($item['isi'] ?? $item['deskripsi'] ?? $item['content'] ?? ''), 100),
                'content' => $item['isi'] ?? $item['deskripsi'] ?? $item['content'] ?? '',
            ];
        });
    }

    /**
     * Map category slug to human readable label.
     */
    protected function getCategoryLabel(?string $category): string
    {
        return match ($category) {
            'hospital_info' => 'Info Rumah Sakit',
            'announcement' => 'Pengumuman',
            'event' => 'Acara',
            'health_news' => 'Berita Kesehatan',
            default => 'Berita Umum',
        };
    }
}
