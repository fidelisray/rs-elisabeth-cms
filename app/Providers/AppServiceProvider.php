<?php

namespace App\Providers;

use App\Services\FeedbackService;
use App\Services\WebFrontendWebhookService;
use Filament\Forms\Components\Field;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Translatable\Facades\Translatable;

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
        Field::macro('clearErrorOnInput', function (): static {
            return $this->extraFieldWrapperAttributes([
                'x-on:input' => "
                    \$el.querySelectorAll('[data-validation-error]').forEach((e) => e.remove());
                    \$el.querySelector('.fi-input-wrp')?.classList.remove('fi-invalid');
                ",
                'x-on:change' => "
                    \$el.querySelectorAll('[data-validation-error]').forEach((e) => e.remove());
                    \$el.querySelector('.fi-input-wrp')?.classList.remove('fi-invalid');
                ",
            ], merge: true);
        });

        Field::macro('clearErrorOnChange', function (): static {
            return $this->clearErrorOnInput();
        });

        Translatable::fallback(
            fallbackLocale: 'id',
            fallbackAny: true,
        )->locales(config('app.supported_locales', ['id', 'en']));
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
