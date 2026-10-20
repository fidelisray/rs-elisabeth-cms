<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Cms\FacilityServiceResource;
use App\Models\FacilityService;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class FacilityServiceApiController extends Controller
{
    /**
     * Mengembalikan daftar fasilitas & layanan yang aktif,
     * diurutkan berdasarkan sort_order lalu name.
     */
    #[OA\Get(
        path: '/api/v1/cms/facilities',
        summary: 'Get list of facilities and services',
        security: [['HmacAuth' => []]],
        tags: ['Facilities'],
        description: 'Returns list of active facilities and services without pagination.'
    )]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function index()
    {
        $facilities = Cache::remember('rs_web_cms_api_facility_services', now()->addHours(6), function () {
            return FacilityService::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name->id')
                ->get();
        });

        return FacilityServiceResource::collection($facilities);
    }

    #[OA\Get(
        path: '/api/v1/cms/facilities/{slug}',
        summary: 'Get facility or service detail',
        security: [['HmacAuth' => []]],
        tags: ['Facilities'],
        description: 'Returns a single facility or service data.'
    )]
    #[OA\Parameter(name: 'slug', description: 'Facility slug', in: 'path', required: true)]
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
