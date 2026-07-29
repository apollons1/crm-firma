<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));

        // Twilio/Stripe nu trimit token CSRF — validăm autenticitatea prin
        // semnătura (X-Twilio-Signature / Stripe-Signature), direct în controller.
        $middleware->validateCsrfTokens(except: [
            'webhooks/twilio/whatsapp',
            'webhooks/stripe',
        ]);

        // Necesar ca validarea semnăturii Twilio să reconstruiască URL-ul
        // corect (https) când aplicația rulează în spatele unui proxy
        // (ngrok local, Cloudflare/Nginx în producție).
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
