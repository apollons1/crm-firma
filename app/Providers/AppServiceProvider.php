<?php

namespace App\Providers;

use App\Services\WhatsAppService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton: un singur client Twilio per request/proces, cu
        // credențialele citite din config/services.php (populate din .env).
        $this->app->singleton(WhatsAppService::class, function ($app) {
            return new WhatsAppService(
                config('services.twilio.sid'),
                config('services.twilio.token'),
                config('services.twilio.whatsapp_from')
            );
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
