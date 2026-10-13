<?php

namespace App\Providers;

use App\Services\FeedbackService;
use App\Services\WebFrontendWebhookService;
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
        //
    }
}
