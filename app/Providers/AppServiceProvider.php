<?php

namespace App\Providers;

use App\Services\WhatsAppService;
use Filament\Support\Facades\FilamentTimezone;
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
        // Stocarea rămâne în UTC (config('app.timezone')) — schimbarea ei ar
        // reinterpreta greșit toate orele deja salvate. Convertim doar la
        // afișare, pentru toate coloanele/câmpurile de dată-oră din Filament.
        FilamentTimezone::set('Europe/Bucharest');
    }
}
