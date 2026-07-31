<?php

namespace App\Listeners;

use App\Models\FailedLoginAttempt;
use Illuminate\Auth\Events\Failed;

/**
 * Înregistrează fiecare încercare eșuată de autentificare din panoul
 * Filament — evidență istorică pentru App\Console\Commands\DetectSuspiciousLoginActivity.
 * NU stochează parola introdusă (Failed::$credentials e marcat
 * #[SensitiveParameter], dar noi oricum extragem doar email-ul).
 */
class RecordFailedLoginAttempt
{
    public function handle(Failed $event): void
    {
        FailedLoginAttempt::create([
            'ip_address' => request()->ip() ?? 'necunoscut',
            'email_attempted' => $event->credentials['email'] ?? $event->user?->email,
            'user_agent' => request()->userAgent(),
            'attempted_at' => now(),
        ]);
    }
}
