<?php

namespace App\Filament\Pages\Auth;

use App\Support\LoginRateLimiter;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Suprascrie două verificări de rate limiting față de implicitul Filament:
 *
 * - rateLimit() (parola): implicit, Filament limitează doar după IP, 5
 *   încercări / 60s, fără escaladare. Aici: 5 încercări per combinație
 *   email+IP / 60s, cu blocare escaladată (1 min → 5 min → 15 min) —
 *   vezi App\Support\LoginRateLimiter.
 * - isMultiFactorChallengeRateLimited() (codul 2FA): implicit, Filament
 *   permite 5 încercări cu blocare de doar 60 de secunde. Aici: 5 coduri
 *   greșite în 10 minute → blocare de 30 de minute.
 */
class Login extends BaseLogin
{
    private const MAX_ATTEMPTS = 5;

    private const ATTEMPT_WINDOW_MINUTES = 10;

    private const LOCKOUT_MINUTES = 30;

    /**
     * @param  int  $maxAttempts  neutilizat — pragul e definit de LoginRateLimiter
     * @param  int  $decaySeconds  neutilizat — vezi mai sus
     */
    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null): void
    {
        $email = (string) ($this->data['email'] ?? '');
        $ip = request()->ip() ?? 'necunoscut';

        $secondsRemaining = LoginRateLimiter::checkAndHit($email, $ip);

        if ($secondsRemaining !== null) {
            throw new TooManyRequestsException(static::class, 'authenticate', $ip, $secondsRemaining);
        }
    }

    protected function isMultiFactorChallengeRateLimited(Authenticatable $user): bool
    {
        $lockKey = "filament-multi-factor-challenge-locked:{$user->getAuthIdentifier()}";

        /** @var ?Carbon $lockedUntil */
        $lockedUntil = Cache::get($lockKey);

        if ($lockedUntil?->isFuture()) {
            $secondsRemaining = (int) now()->diffInSeconds($lockedUntil, absolute: true);

            $this->logMultiFactorChallengeBlocked($user, $secondsRemaining, newlyLocked: false);

            $this->getRateLimitedNotification(new TooManyRequestsException(
                static::class,
                'authenticate',
                request()->ip(),
                $secondsRemaining,
            ))?->send();

            return true;
        }

        $attemptsKey = "filament-multi-factor-challenge:{$user->getAuthIdentifier()}";

        if (RateLimiter::tooManyAttempts($attemptsKey, maxAttempts: self::MAX_ATTEMPTS)) {
            RateLimiter::clear($attemptsKey);

            $lockedUntil = now()->addMinutes(self::LOCKOUT_MINUTES);
            Cache::put($lockKey, $lockedUntil, $lockedUntil);

            $this->logMultiFactorChallengeBlocked($user, self::LOCKOUT_MINUTES * 60, newlyLocked: true);

            $this->getRateLimitedNotification(new TooManyRequestsException(
                static::class,
                'authenticate',
                request()->ip(),
                self::LOCKOUT_MINUTES * 60,
            ))?->send();

            return true;
        }

        RateLimiter::hit($attemptsKey, self::ATTEMPT_WINDOW_MINUTES * 60);

        return false;
    }

    /**
     * Nu logăm codul 2FA introdus — doar identificatorul userului, IP-ul
     * și durata blocării.
     */
    private function logMultiFactorChallengeBlocked(Authenticatable $user, int $secondsRemaining, bool $newlyLocked): void
    {
        Log::warning('Verificare 2FA blocată — prea multe coduri greșite.', [
            'user_id' => $user->getAuthIdentifier(),
            'ip' => request()->ip(),
            'seconds_remaining' => $secondsRemaining,
            'newly_locked' => $newlyLocked,
        ]);
    }
}
