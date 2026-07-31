<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Rate limiting pentru formularul de login, împotriva atacurilor
 * brute-force: maxim 5 încercări per combinație email+IP într-o fereastră
 * de 1 minut, apoi blocare temporară cu backoff progresiv (1 min → 5 min
 * → 15 min) dacă aceeași identitate declanșează blocarea repetat într-un
 * interval de 24h.
 *
 * Cheia e email+IP (nu doar IP, ca implicit în Filament) — un atacator
 * care încearcă multe conturi de pe aceeași adresă IP nu ar trebui să
 * poată bloca alte conturi legitime doar epuizând contorul unui singur
 * cont, și invers.
 */
class LoginRateLimiter
{
    private const MAX_ATTEMPTS = 5;

    private const ATTEMPT_WINDOW_SECONDS = 60;

    private const VIOLATION_TRACKING_HOURS = 24;

    /**
     * Durata blocării (minute), în ordinea escaladării: prima blocare,
     * a doua, a treia și oricâte următoare (rămâne la ultima valoare).
     *
     * @var array<int, int>
     */
    private const LOCK_MINUTES_ESCALATION = [1, 5, 15];

    public static function identityKey(string $email, string $ip): string
    {
        return sha1(mb_strtolower(trim($email)).'|'.$ip);
    }

    /**
     * Verifică și înregistrează o încercare de autentificare. Întoarce
     * numărul de secunde rămase dacă identitatea (email+IP) e în acest
     * moment blocată, altfel null — caz în care încercarea curentă a fost
     * numărată în fereastra activă.
     */
    public static function checkAndHit(string $email, string $ip): ?int
    {
        $identityKey = self::identityKey($email, $ip);
        $lockKey = self::lockCacheKey($identityKey);

        $lockedUntil = Cache::get($lockKey);

        if ($lockedUntil instanceof Carbon && $lockedUntil->isFuture()) {
            $secondsRemaining = (int) now()->diffInSeconds($lockedUntil, true);

            self::logBlocked($email, $ip, $secondsRemaining, newlyLocked: false);

            return $secondsRemaining;
        }

        $attemptsKey = self::attemptsCacheKey($identityKey);

        if (RateLimiter::tooManyAttempts($attemptsKey, self::MAX_ATTEMPTS)) {
            RateLimiter::clear($attemptsKey);

            $lockMinutes = self::nextLockMinutes($identityKey);
            $lockedUntil = now()->addMinutes($lockMinutes);
            Cache::put($lockKey, $lockedUntil, $lockedUntil);

            self::logBlocked($email, $ip, $lockMinutes * 60, newlyLocked: true, lockMinutes: $lockMinutes);

            return $lockMinutes * 60;
        }

        RateLimiter::hit($attemptsKey, self::ATTEMPT_WINDOW_SECONDS);

        return null;
    }

    /**
     * Șterge contorul de încercări și orice blocare activă pentru
     * identitatea dată — apelat la autentificare reușită, ca un user
     * legitim care a greșit parola de câteva ori să nu rămână cu
     * contorul parțial consumat. Contorul de "violări" (folosit pentru
     * escaladare) NU se șterge aici — o autentificare reușită la mijlocul
     * unui atac (ex: parolă compromisă) nu ar trebui să reseteze
     * escaladarea pentru acea identitate.
     */
    public static function clear(string $email, string $ip): void
    {
        $identityKey = self::identityKey($email, $ip);

        RateLimiter::clear(self::attemptsCacheKey($identityKey));
        Cache::forget(self::lockCacheKey($identityKey));
    }

    private static function nextLockMinutes(string $identityKey): int
    {
        $violationsKey = self::violationsCacheKey($identityKey);
        $violationCount = (int) Cache::get($violationsKey, 0);

        Cache::put($violationsKey, $violationCount + 1, now()->addHours(self::VIOLATION_TRACKING_HOURS));

        $index = min($violationCount, count(self::LOCK_MINUTES_ESCALATION) - 1);

        return self::LOCK_MINUTES_ESCALATION[$index];
    }

    private static function attemptsCacheKey(string $identityKey): string
    {
        return "auth-rate-limit:login:attempts:{$identityKey}";
    }

    private static function lockCacheKey(string $identityKey): string
    {
        return "auth-rate-limit:login:locked:{$identityKey}";
    }

    private static function violationsCacheKey(string $identityKey): string
    {
        return "auth-rate-limit:login:violations:{$identityKey}";
    }

    /**
     * Nu logăm parola sau alte date sensibile — doar emailul (necesar
     * pentru monitorizare/audit) și IP-ul, ambele deja vizibile oricui
     * are acces la formularul de login.
     */
    private static function logBlocked(
        string $email,
        string $ip,
        int $secondsRemaining,
        bool $newlyLocked,
        ?int $lockMinutes = null,
    ): void {
        Log::warning('Autentificare blocată — prea multe încercări eșuate.', [
            'email' => $email,
            'ip' => $ip,
            'seconds_remaining' => $secondsRemaining,
            'newly_locked' => $newlyLocked,
            'lock_minutes' => $lockMinutes,
        ]);
    }
}
