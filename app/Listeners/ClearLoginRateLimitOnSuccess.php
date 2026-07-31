<?php

namespace App\Listeners;

use App\Support\LoginRateLimiter;
use Illuminate\Auth\Events\Login;

/**
 * La autentificare reușită, șterge contorul de încercări eșuate pentru
 * combinația email+IP — altfel un user legitim care a greșit parola de
 * 2-3 ori ar rămâne cu contorul parțial consumat pentru următorul minut,
 * fără niciun motiv de securitate real.
 */
class ClearLoginRateLimitOnSuccess
{
    public function handle(Login $event): void
    {
        $email = (string) ($event->user->email ?? '');

        if ($email === '') {
            return;
        }

        LoginRateLimiter::clear($email, request()->ip() ?? 'necunoscut');
    }
}
