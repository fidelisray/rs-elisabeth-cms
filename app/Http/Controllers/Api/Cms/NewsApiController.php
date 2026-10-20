<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Cms\NewsResource;
use App\Models\News;
use App\Support\PageParameter;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class NewsApiController extends Controller
{
    #[OA\Get(
        path: '/api/v1/cms/news',
        summary: 'Get list of news',
        security: [['HmacAuth' => []]],
        tags: ['News'],
        description: 'Returns list of published news with pagination.'
    )]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function index()
    {
        $page = PageParameter::from(request());
        $version = Cache::rememberForever('rs_web_cms_api_news_version', fn () => time());

        $news = Cache::remember("rs_web_cms_api_news_v{$version}_page_{$page}", now()->addHours(2), function () use ($page) {
            return News::query()
                ->where('is_published', true)
                ->latest()
                ->paginate(12, ['*'], 'page', $page);
        });

        return NewsResource::collection($news);
    }

    #[OA\Get(
        path: '/api/v1/cms/news/{slug}',
        summary: 'Get news detail',
        security: [['HmacAuth' => []]],
        tags: ['News'],
        description: 'Returns a single news data.'
    )]
    #[OA\Parameter(name: 'slug', description: 'News slug', in: 'path', required: true)]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Resource Not Found')]
    public function show($slug)
    {
        $news = News::query()
            ->where('is_published', true)
            ->whereTranslatedSlug($slug)
            ->firstOrFail();

        return new NewsResource($news);
    }
}
