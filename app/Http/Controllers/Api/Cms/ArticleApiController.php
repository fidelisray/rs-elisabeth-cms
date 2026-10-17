<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Cms\ArticleResource;
use App\Models\Article;
use App\Support\PageParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class ArticleApiController extends Controller
{
    #[OA\Get(
        path: '/api/v1/cms/articles',
        summary: 'Get list of articles',
        security: [['HmacAuth' => []]],
        tags: ['Articles'],
        description: 'Returns list of active articles with pagination.'
    )]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function index()
    {
        $page = PageParameter::from(request());
        $version = Cache::rememberForever('rs_web_cms_api_articles_version', fn () => time());

        $articles = Cache::remember("rs_web_cms_api_articles_v{$version}_page_{$page}", now()->addHours(2), function () use ($page) {
            return Article::query()
                ->where('is_active', 'yes')
                ->latest()
                ->paginate(12, ['*'], 'page', $page);
        });

        return ArticleResource::collection($articles);
    }

    #[OA\Get(
        path: '/api/v1/cms/articles/{identifier}',
        summary: 'Get article detail',
        security: [['HmacAuth' => []]],
        tags: ['Articles'],
        description: 'Returns a single article data. Accepts numeric ID or slug as the identifier.'
    )]
    #[OA\Parameter(name: 'identifier', description: 'Article ID (numeric) or slug (string)', in: 'path', required: true)]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Resource Not Found')]
    public function show($identifier): JsonResponse
    {
        $query = Article::query()->where('is_active', 'yes');

        // Smart identifier: angka = lookup by ID, selain itu = lookup by slug.
        if (is_numeric($identifier)) {
            $query->where('id', (int) $identifier);
        } else {
            $query->where('slug', $identifier);
        }

        $article = $query->first();

        if (! $article) {
            return response()->json([
                'success' => false,
                'status_code' => 404,
                'message' => 'Artikel tidak ditemukan.',
            ], 404);
        }

        return (new ArticleResource($article))->response();
    }
}
