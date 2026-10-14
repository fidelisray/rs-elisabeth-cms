<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Cms\PromotionResource;
use App\Models\Promotion;
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
        $page = request('page', 1);
        $version = Cache::rememberForever('rs_web_cms_api_promotions_version', fn () => time());

        $promotions = Cache::remember("rs_web_cms_api_promotions_v{$version}_page_{$page}", now()->addHours(2), function () {
            return Promotion::query()
                ->where('is_active', true)
                ->latest()
                ->paginate(12);
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
        $query = Promotion::query()->where('is_active', true);

        // Smart identifier: angka = lookup by ID, selain itu = lookup by slug.
        if (is_numeric($identifier)) {
            $query->where('id', (int) $identifier);
        } else {
            $query->where('slug', $identifier);
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
