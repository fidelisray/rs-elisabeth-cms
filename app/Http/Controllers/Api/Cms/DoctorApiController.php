<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Cms\DoctorResource;
use App\Models\Doctor;
use App\Support\ApiCacheInvalidator;
use App\Support\TranslatedContentOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DoctorApiController extends Controller
{
    /**
     * Mengembalikan daftar seluruh dokter aktif beserta data profil marketing.
     */
    public function index(Request $request)
    {
        $locale = TranslatedContentOrder::locale(app()->getLocale());
        $specialty = $request->query('specialty');

        $cacheKey = ApiCacheInvalidator::keyForModule('doctors', $locale).($specialty ? '_'.md5($specialty) : '');

        $doctors = Cache::remember($cacheKey, now()->addHours(6), function () use ($specialty) {
            $query = Doctor::query()
                ->where('is_active', true)
                ->orderBy('name');

            if ($specialty) {
                $query->where(function ($q) use ($specialty) {
                    $q->where('specialty_code', $specialty)
                        ->orWhere('specialty_name', $specialty);
                });
            }

            return $query->get();
        });

        return DoctorResource::collection($doctors);
    }

    /**
     * Mengembalikan profil satu dokter berdasarkan kode paramedis SIMRS.
     */
    public function show(string $paramedicCode)
    {
        $doctor = Doctor::query()
            ->where('paramedic_code', $paramedicCode)
            ->where('is_active', true)
            ->firstOrFail();

        return new DoctorResource($doctor);
    }
}
