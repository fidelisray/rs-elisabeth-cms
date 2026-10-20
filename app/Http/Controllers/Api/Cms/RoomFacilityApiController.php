<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Cms\RoomFacilityResource;
use App\Models\RoomFacility;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class RoomFacilityApiController extends Controller
{
    /**
     * Mengembalikan daftar ruang perawatan yang aktif,
     * diurutkan berdasarkan sort_order, beserta URL foto.
     */
    #[OA\Get(
        path: '/api/v1/cms/room-facilities',
        summary: 'Get list of room facilities',
        security: [['HmacAuth' => []]],
        tags: ['Room Facilities'],
        description: 'Returns list of active room facilities without pagination.'
    )]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function index()
    {
        $rooms = Cache::remember('rs_web_cms_api_room_facilities', now()->addHours(6), function () {
            return RoomFacility::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name->id')
                ->get();
        });

        return RoomFacilityResource::collection($rooms);
    }

    #[OA\Get(
        path: '/api/v1/cms/room-facilities/{slug}',
        summary: 'Get room facility detail',
        security: [['HmacAuth' => []]],
        tags: ['Room Facilities'],
        description: 'Returns a single room facility data.'
    )]
    #[OA\Parameter(name: 'slug', description: 'Room Facility slug', in: 'path', required: true)]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Resource Not Found')]
    public function show($slug)
    {
        $room = RoomFacility::query()
            ->where('is_active', true)
            ->whereTranslatedSlug($slug)
            ->firstOrFail();

        return new RoomFacilityResource($room);
    }
}
