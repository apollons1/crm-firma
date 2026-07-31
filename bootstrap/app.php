<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));

        // Twilio/Stripe/UptimeRobot nu trimit token CSRF — autenticitatea se
        // verifică altfel, direct în controller (semnătură, respectiv token
        // din URL).
        $middleware->validateCsrfTokens(except: [
            'webhooks/twilio/whatsapp',
            'webhooks/stripe',
            'webhooks/uptimerobot/*',
        ]);

        // Necesar ca validarea semnăturii Twilio să reconstruiască URL-ul
        // corect (https) când aplicația rulează în spatele unui proxy
        // (ngrok local, Cloudflare/Nginx în producție).
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);
    })->create();
