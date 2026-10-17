<?php

namespace App\Providers;

use App\Services\FeedbackService;
use App\Services\WebFrontendWebhookService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->singleton(FeedbackService::class, function ($app) {
            return new FeedbackService;
        });

        $this->app->singleton(WebFrontendWebhookService::class, function ($app) {
            return new WebFrontendWebhookService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $tooMany = fn (Request $request, array $headers) => response()->json([
            'success' => false,
            'status_code' => 429,
            'message' => 'Too many requests.',
            'errors' => null,
        ], 429, $headers);

        // Batas per IP untuk SEMUA request API CMS, termasuk yang akan gagal HMAC, sehingga
        // banjir dan tebakan signature tetap dibatasi. Batas per klien (X-Cons-ID) dihitung
        // di VerifyCmsHmacSignature setelah signature valid.
        RateLimiter::for('cms-api-ip', fn (Request $request) => Limit::perMinute(
            (int) config('cms_api.rate_limit_per_ip_per_minute', 300)
        )->by('cms-api-ip:'.$request->ip())->response($tooMany));

    }
}
