<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Cms\PromotionResource;
use App\Models\Promotion;
use App\Support\ApiCacheInvalidator;
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
        $locale = app()->getLocale();

        // Tanggal hari ini ikut di cache key: tanpa itu, promo yang berakhir atau baru
        // mulai pada pergantian hari tetap salah tampil sampai TTL cache habis, karena
        // pergantian tanggal tidak memicu event model apa pun.
        $today = Promotion::today();

        $promotions = Cache::remember(ApiCacheInvalidator::pageKeyForModule('promotions', $page, $locale, $today), now()->addHours(2), function () use ($today, $page) {
            return Promotion::query()
                ->activeOn($today)
                ->latest()
                ->paginate(12, ['*'], 'page', $page);
        });

        $pagination = (clone $promotions)->withPath(request()->url())->appends('lang', $locale);

        return PromotionResource::collection($pagination);
    }

    #[OA\Get(
        path: '/api/v1/cms/promotions/{identifier}',
        summary: 'Get promotion detail',
        security: [['HmacAuth' => []]],
        tags: ['Promotions'],
        description: 'Returns a currently active promotion by numeric ID or by the exact slug in the selected locale. Slugs from other locales return 404. Use alternate_slugs to switch languages.'
    )]
    #[OA\Parameter(name: 'identifier', description: 'Promotion ID (numeric) or slug (string)', in: 'path', required: true)]
    #[OA\Parameter(name: 'lang', description: 'Content locale: id or en. Unsupported values use id.', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['id', 'en'], default: 'id'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Resource Not Found')]
    public function show($identifier): JsonResponse
    {
        // Filter tanggal yang sama dengan daftar, supaya promo yang sudah lewat atau belum mulai
        // tidak bisa diakses langsung lewat ID maupun slug.
        $query = Promotion::query()->activeOn();

        // Identifier numerik tetap lookup by ID; slug harus cocok dengan locale request.
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
