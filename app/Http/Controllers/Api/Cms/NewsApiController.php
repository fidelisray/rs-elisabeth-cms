<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Cms\NewsResource;
use App\Models\News;
use App\Support\ApiCacheInvalidator;
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
        $locale = app()->getLocale();

        $news = Cache::remember(ApiCacheInvalidator::pageKeyForModule('news', $page, $locale), now()->addHours(2), function () use ($page) {
            return News::query()
                ->where('is_published', true)
                ->latest()
                ->paginate(12, ['*'], 'page', $page);
        });

        // Rebuild request-specific pagination links without mutating the cached paginator.
        $pagination = (clone $news)->withPath(request()->url())->appends('lang', $locale);

        return NewsResource::collection($pagination);
    }

    #[OA\Get(
        path: '/api/v1/cms/news/{slug}',
        summary: 'Get news detail',
        security: [['HmacAuth' => []]],
        tags: ['News'],
        description: 'Returns published news matching the exact slug in the selected locale. Slugs from other locales return 404. Use alternate_slugs to switch languages.'
    )]
    #[OA\Parameter(name: 'slug', description: 'News slug', in: 'path', required: true)]
    #[OA\Parameter(name: 'lang', description: 'Content locale: id or en. Unsupported values use id.', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['id', 'en'], default: 'id'))]
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
