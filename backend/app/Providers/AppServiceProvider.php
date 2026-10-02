<?php

namespace App\Providers;

use App\Services\Ollama\OllamaClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OllamaClient::class, fn () => new OllamaClient(
            baseUrl: config('services.ollama.base_url'),
            timeout: config('services.ollama.timeout'),
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
