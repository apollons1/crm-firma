<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use App\Services\StripeService;
use App\Services\WhatsAppService;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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

        // Singleton: un singur client Stripe per request/proces.
        $this->app->singleton(StripeService::class, function ($app) {
            return new StripeService(
                config('services.stripe.secret')
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

        // Istoric parole, password_changed_at și notificarea de schimbare
        // a parolei — vezi App\Observers\UserObserver.
        User::observe(UserObserver::class);

        // Politica minimă de parole (lungime, litere mari/mici, simbol,
        // parole compromise) — aplicată global oriunde se folosește
        // Password::default()/Password::defaults(), inclusiv pe pagina
        // de profil (schimbare parolă proprie). Regulile suplimentare
        // (min. 2 cifre, nu conține nume/email, istoric ultimele 5 parole)
        // sunt în App\Rules\StrongPassword, aplicat separat pe fiecare câmp.
        Password::defaults(fn () => Password::min(12)
            ->mixedCase()
            ->symbols()
            ->uncompromised());
    }
}
