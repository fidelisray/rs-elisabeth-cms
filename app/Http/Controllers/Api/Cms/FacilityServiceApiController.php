<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Cms\FacilityServiceResource;
use App\Models\FacilityService;
use App\Support\ApiCacheInvalidator;
use App\Support\TranslatedContentOrder;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class FacilityServiceApiController extends Controller
{
    /**
     * Mengembalikan daftar fasilitas & layanan yang aktif,
     * diurutkan berdasarkan sort_order, nama pada locale request, lalu ID.
     */
    #[OA\Get(
        path: '/api/v1/cms/facilities',
        summary: 'Get list of facilities and services',
        security: [['HmacAuth' => []]],
        tags: ['Facilities'],
        description: 'Returns active facilities without pagination, ordered by sort_order, name in the selected locale, and ID.'
    )]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function index()
    {
        $locale = TranslatedContentOrder::locale(app()->getLocale());
        $facilities = Cache::remember(ApiCacheInvalidator::keyForModule('facilities', $locale), now()->addHours(6), function () use ($locale) {
            $query = FacilityService::query()
                ->where('is_active', true)
                ->orderBy('sort_order');

            return TranslatedContentOrder::apply($query, 'name', $locale)
                ->orderBy($query->getModel()->getQualifiedKeyName())->get();
        });

        return FacilityServiceResource::collection($facilities);
    }

    #[OA\Get(
        path: '/api/v1/cms/facilities/{slug}',
        summary: 'Get facility or service detail',
        security: [['HmacAuth' => []]],
        tags: ['Facilities'],
        description: 'Returns an active facility matching the exact slug in the selected locale. Slugs from other locales return 404. Use alternate_slugs to switch languages.'
    )]
    #[OA\Parameter(name: 'slug', description: 'Facility slug', in: 'path', required: true)]
    #[OA\Parameter(name: 'lang', description: 'Content locale: id or en. Unsupported values use id.', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['id', 'en'], default: 'id'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Resource Not Found')]
    public function show($slug)
    {
        $facility = FacilityService::query()
            ->where('is_active', true)
            ->whereTranslatedSlug($slug)
            ->firstOrFail();

        return new FacilityServiceResource($facility);
    }
}
