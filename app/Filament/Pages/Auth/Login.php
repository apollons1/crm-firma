<?php

namespace App\Filament\Pages\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Suprascrie regula de limitare a încercărilor la codul 2FA: implicit,
 * Filament permite 5 încercări cu blocare de doar 60 de secunde. Aici:
 * 5 coduri greșite în 10 minute → blocare de 30 de minute.
 */
class Login extends BaseLogin
{
    private const MAX_ATTEMPTS = 5;

    private const ATTEMPT_WINDOW_MINUTES = 10;

    private const LOCKOUT_MINUTES = 30;

    protected function isMultiFactorChallengeRateLimited(Authenticatable $user): bool
    {
        $lockKey = "filament-multi-factor-challenge-locked:{$user->getAuthIdentifier()}";

        /** @var ?Carbon $lockedUntil */
        $lockedUntil = Cache::get($lockKey);

        if ($lockedUntil?->isFuture()) {
            $this->getRateLimitedNotification(new TooManyRequestsException(
                static::class,
                'authenticate',
                request()->ip(),
                (int) now()->diffInSeconds($lockedUntil, absolute: true),
            ))?->send();

            return true;
        }

        $attemptsKey = "filament-multi-factor-challenge:{$user->getAuthIdentifier()}";

        if (RateLimiter::tooManyAttempts($attemptsKey, maxAttempts: self::MAX_ATTEMPTS)) {
            RateLimiter::clear($attemptsKey);

            $lockedUntil = now()->addMinutes(self::LOCKOUT_MINUTES);
            Cache::put($lockKey, $lockedUntil, $lockedUntil);

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
}
