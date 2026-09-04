<?php

namespace App\Providers;

use App\Services\Ml\LandAnalysisClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LandAnalysisClient::class, fn (): LandAnalysisClient => new LandAnalysisClient(
            baseUrl: (string) config('services.ml.url', 'http://localhost:8001'),
            apiKey: config('services.ml.key'),
            timeout: (int) config('services.ml.timeout', 60),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
