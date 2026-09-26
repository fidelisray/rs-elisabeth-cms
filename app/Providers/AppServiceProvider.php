<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\FeedbackService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->singleton(FeedbackService::class, function ($app) {
            return new FeedbackService();
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
