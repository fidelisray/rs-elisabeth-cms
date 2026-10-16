<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Cms\BannerPromotionResource;
use App\Models\BannerPromotion;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class BannerPromotionApiController extends Controller
{
    /**
     * Mengembalikan daftar banner yang aktif, diurutkan berdasarkan sort_order.
     * URL gambar dikembalikan sebagai absolute URL agar bisa diakses langsung.
     */
    #[OA\Get(
        path: '/api/v1/cms/banner-promotions',
        summary: 'Get list of banner promotions',
        security: [['HmacAuth' => []]],
        tags: ['Banner Promotions'],
        description: 'Returns list of active banner promotions without pagination.'
    )]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function index()
    {
        $banners = Cache::remember('rs_web_cms_api_banner_promotions', now()->addHours(6), function () {
            return BannerPromotion::query()
                ->where('is_active', true)
                ->orderBy('sort_order', 'asc')
                ->get();
        });

        return BannerPromotionResource::collection($banners);
    }
}
