<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Cms\PromotionResource;
use App\Models\Promotion;
use App\Support\PageParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class PromotionApiController extends Controller
{
    #[OA\Get(
        path: '/api/v1/cms/promotions',
        summary: 'Get list of promotions',
        security: [['HmacAuth' => []]],
        tags: ['Promotions'],
        description: 'Returns list of active promotions with pagination.'
    )]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function index()
    {
        $page = PageParameter::from(request());
        $version = Cache::rememberForever('rs_web_cms_api_promotions_version', fn () => time());

        // Tanggal hari ini ikut di cache key: tanpa itu, promo yang berakhir atau baru
        // mulai pada pergantian hari tetap salah tampil sampai TTL cache habis, karena
        // pergantian tanggal tidak memicu event model apa pun.
        $today = Promotion::today();

        $promotions = Cache::remember("rs_web_cms_api_promotions_v{$version}_{$today}_page_{$page}", now()->addHours(2), function () use ($today, $page) {
            return Promotion::query()
                ->activeOn($today)
                ->latest()
                ->paginate(12, ['*'], 'page', $page);
        });

        return PromotionResource::collection($promotions);
    }

    #[OA\Get(
        path: '/api/v1/cms/promotions/{identifier}',
        summary: 'Get promotion detail',
        security: [['HmacAuth' => []]],
        tags: ['Promotions'],
        description: 'Returns a single promotion data. Accepts numeric ID or slug as the identifier.'
    )]
    #[OA\Parameter(name: 'identifier', description: 'Promotion ID (numeric) or slug (string)', in: 'path', required: true)]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Resource Not Found')]
    public function show($identifier): JsonResponse
    {
        // Filter tanggal yang sama dengan daftar, supaya promo yang sudah lewat atau belum mulai
        // tidak bisa diakses langsung lewat ID maupun slug.
        $query = Promotion::query()->activeOn();

        // Smart identifier: angka = lookup by ID, selain itu = lookup by slug multi-bahasa.
        if (is_numeric($identifier)) {
            $query->where('id', (int) $identifier);
        } else {
            $query->whereTranslatedSlug($identifier);
        }

        $promotion = $query->first();

        if (! $promotion) {
            return response()->json([
                'success' => false,
                'status_code' => 404,
                'message' => 'Promosi tidak ditemukan.',
            ], 404);
        }

        return (new PromotionResource($promotion))->response();
    }
}
