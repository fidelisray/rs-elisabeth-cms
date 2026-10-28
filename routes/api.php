<?php

use App\Http\Controllers\Api\Cms\ArticleApiController;
use App\Http\Controllers\Api\Cms\BannerPromotionApiController;
use App\Http\Controllers\Api\Cms\DoctorApiController;
use App\Http\Controllers\Api\Cms\FacilityServiceApiController;
use App\Http\Controllers\Api\Cms\FeedbackApiController;
use App\Http\Controllers\Api\Cms\NewsApiController;
use App\Http\Controllers\Api\Cms\OnlineLibraryApiController;
use App\Http\Controllers\Api\Cms\PromotionApiController;
use App\Http\Controllers\Api\Cms\QuestionnaireApiController;
use App\Http\Controllers\Api\Cms\RoomFacilityApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1/cms')
    // Batas per IP berjalan lebih dulu (untuk semua request, termasuk yang gagal HMAC).
    // Batas per klien dihitung di dalam verifyCmsHmac setelah signature valid.
    ->middleware(['throttleCmsIp', 'verifyCmsHmac', 'setCmsLocale'])
    ->group(function () {
        Route::get('/news', [NewsApiController::class, 'index']);
        Route::get('/news/{slug}', [NewsApiController::class, 'show']);

        Route::get('/articles', [ArticleApiController::class, 'index']);
        Route::get('/articles/{identifier}', [ArticleApiController::class, 'show']);

        Route::get('/promotions', [PromotionApiController::class, 'index']);
        Route::get('/promotions/{identifier}', [PromotionApiController::class, 'show']);

        Route::get('/facilities', [FacilityServiceApiController::class, 'index']);
        Route::get('/facilities/{slug}', [FacilityServiceApiController::class, 'show']);

        // Room Facilities (Ruang Perawatan)
        Route::get('/room-facilities', [RoomFacilityApiController::class, 'index']);
        Route::get('/room-facilities/{slug}', [RoomFacilityApiController::class, 'show']);

        // Banner Promotions (Carousel Halaman Utama)
        Route::get('/banner-promotions', [BannerPromotionApiController::class, 'index']);

        // Doctors (Profil Dokter)
        Route::get('/doctors', [DoctorApiController::class, 'index']);
        Route::get('/doctors/{paramedicCode}', [DoctorApiController::class, 'show']);

        // Perpustakaan Online (Online Library Documents)
        Route::get('/online-libraries', [OnlineLibraryApiController::class, 'index']);
        Route::get('/online-libraries/{identifier}', [OnlineLibraryApiController::class, 'show']);

        // Kritik dan saran pasien (Web Frontend, POST). Body ikut ditandatangani HMAC.
        Route::post('/feedbacks', [FeedbackApiController::class, 'store']);

        // Kuesioner kepuasan pasien. Jenis kuesioner dari URL: igd, rawat-jalan, rawat-inap.
        Route::post('/questionnaires/{type}', [QuestionnaireApiController::class, 'store'])
            ->where('type', 'igd|rawat-jalan|rawat-inap');
    });
