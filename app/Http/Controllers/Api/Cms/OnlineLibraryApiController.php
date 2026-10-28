<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Cms\OnlineLibraryResource;
use App\Models\OnlineLibrary;
use App\Support\ApiCacheInvalidator;
use App\Support\PageParameter;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class OnlineLibraryApiController extends Controller
{
    #[OA\Get(
        path: '/api/v1/cms/online-libraries',
        summary: 'Get list of online library documents',
        security: [['HmacAuth' => []]],
        tags: ['OnlineLibrary'],
        description: 'Returns list of published online library documents with pagination.'
    )]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function index()
    {
        $page = PageParameter::from(request());
        $locale = app()->getLocale();

        $documents = Cache::remember(ApiCacheInvalidator::pageKeyForModule('online_libraries', $page, $locale), now()->addHours(2), function () use ($page) {
            return OnlineLibrary::query()
                ->where('is_published', true)
                ->latest()
                ->paginate(12, ['*'], 'page', $page);
        });

        // Rebuild request-specific pagination links without mutating the cached paginator.
        $pagination = (clone $documents)->withPath(request()->url())->appends('lang', $locale);

        return OnlineLibraryResource::collection($pagination);
    }

    #[OA\Get(
        path: '/api/v1/cms/online-libraries/{identifier}',
        summary: 'Get online library document detail',
        security: [['HmacAuth' => []]],
        tags: ['OnlineLibrary'],
        description: 'Returns published document matching the exact slug in the selected locale.'
    )]
    #[OA\Parameter(name: 'identifier', description: 'Document slug', in: 'path', required: true)]
    #[OA\Parameter(name: 'lang', description: 'Content locale: id or en.', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['id', 'en'], default: 'id'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Resource Not Found')]
    public function show($identifier)
    {
        $document = OnlineLibrary::query()
            ->where('is_published', true)
            ->whereTranslatedSlug($identifier)
            ->firstOrFail();

        return new OnlineLibraryResource($document);
    }
}
