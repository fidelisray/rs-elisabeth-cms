<?php

namespace App\Http\Controllers\Api\Cms;

use App\Enums\DynamicFormMode;
use App\Enums\DynamicFormStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Cms\DynamicFormListResource;
use App\Http\Resources\Api\Cms\DynamicFormSchemaResource;
use App\Models\DynamicForm;
use App\Support\ApiCacheInvalidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class DynamicFormApiController extends Controller
{
    #[OA\Get(
        path: '/api/v1/cms/forms',
        summary: 'Get list of active published dynamic forms',
        security: [['HmacAuth' => []]],
        tags: ['DynamicForms'],
        description: 'Returns published dynamic forms currently active by schedule. Defaults to public-listed forms only (is_public_listed = true) unless include_unlisted=1 is passed.'
    )]
    #[OA\Parameter(name: 'mode', description: 'Form mode: questionnaire or feedback', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['questionnaire', 'feedback']))]
    #[OA\Parameter(name: 'lang', description: 'Form language: id or en', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['id', 'en']))]
    #[OA\Parameter(name: 'listed_only', description: 'When 1/true, force returning only public-listed forms', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'include_unlisted', description: 'When 1/true (and listed_only is not set), include unlisted QR/direct-link forms', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function index(Request $request): JsonResponse
    {
        $mode = $request->query('mode');
        $lang = $request->query('lang');

        $validMode = is_string($mode) && DynamicFormMode::tryFrom($mode) ? $mode : null;
        $validLang = is_string($lang) && in_array($lang, ['id', 'en'], true) ? $lang : null;
        $includeUnlisted = $request->boolean('include_unlisted') && ! $request->boolean('listed_only');

        $cacheKey = ApiCacheInvalidator::dynamicFormListKey($validLang ?? 'all', $validMode, $includeUnlisted);

        // Cache daftar form terpublikasi berdasarkan versi invalidasi, lalu saring jadwal real-time agar batas waktu selalu akurat.
        $publishedForms = Cache::remember($cacheKey, now()->addHours(2), function () use ($validMode, $validLang, $includeUnlisted) {
            return DynamicForm::query()
                ->with('publishedVersion')
                ->where('status', DynamicFormStatus::Published->value)
                ->whereNotNull('published_version_id')
                ->when(! $includeUnlisted, fn ($q) => $q->where('is_public_listed', true))
                ->when($validMode !== null, fn ($q) => $q->where('mode', $validMode))
                ->when($validLang !== null, fn ($q) => $q->where('locale', $validLang))
                ->orderByDesc('updated_at')
                ->get();
        });

        $now = now();
        $activeForms = $publishedForms->filter(function (DynamicForm $form) use ($now): bool {
            if ($form->starts_at && $now->lt($form->starts_at)) {
                return false;
            }
            if ($form->ends_at && $now->gt($form->ends_at)) {
                return false;
            }

            return true;
        })->values();

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'data' => DynamicFormListResource::collection($activeForms)->resolve(),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/cms/forms/{slug}',
        summary: 'Get published dynamic form schema and availability',
        security: [['HmacAuth' => []]],
        tags: ['DynamicForms'],
        description: 'Returns published form schema snapshot, conditional logic, and live quota/schedule availability.'
    )]
    #[OA\Parameter(name: 'slug', description: 'Dynamic form slug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Form not found or still draft')]
    public function show(string $slug): JsonResponse
    {
        $cacheKey = ApiCacheInvalidator::dynamicFormDetailKey($slug);

        $form = Cache::remember($cacheKey, now()->addHours(2), function () use ($slug) {
            return DynamicForm::query()
                ->with('publishedVersion')
                ->where('slug', $slug)
                ->whereNotNull('published_version_id')
                ->whereIn('status', [DynamicFormStatus::Published->value, DynamicFormStatus::Closed->value])
                ->first();
        });

        if (! $form) {
            return response()->json([
                'success' => false,
                'status_code' => 404,
                'message' => 'Formulir tidak ditemukan.',
                'errors' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'data' => (new DynamicFormSchemaResource($form))->resolve(),
        ]);
    }
}
