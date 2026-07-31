<?php

namespace Tests\Feature;

use App\Support\LoginRateLimiter;
use Tests\TestCase;

/**
 * Testează direct App\Support\LoginRateLimiter — nu prin formularul Livewire
 * de login, ci pe logica de numărare/blocare în sine: 5 încercări permise
 * per combinație email+IP într-o fereastră de 60s, a 6-a blochează cu
 * backoff escaladat (1 min → 5 min → 15 min), succesul (clear()) resetează
 * doar contorul curent de încercări, nu și nivelul de escaladare.
 */
class LoginRateLimiterTest extends TestCase
{
    private const EMAIL = 'test@example.com';

    private const IP = '203.0.113.10';

    public function test_allows_up_to_five_attempts_before_locking(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->assertNull(
                LoginRateLimiter::checkAndHit(self::EMAIL, self::IP),
                "Încercarea #{$i} nu ar trebui să fie blocată."
            );
        }
    }

    public function test_sixth_attempt_is_blocked_for_one_minute(): void
    {
        for ($i = 0; $i < 5; $i++) {
            LoginRateLimiter::checkAndHit(self::EMAIL, self::IP);
        }

        $secondsRemaining = LoginRateLimiter::checkAndHit(self::EMAIL, self::IP);

        $this->assertNotNull($secondsRemaining);
        $this->assertGreaterThan(0, $secondsRemaining);
        $this->assertLessThanOrEqual(60, $secondsRemaining);
    }

    public function test_block_expires_after_its_duration(): void
    {
        for ($i = 0; $i < 6; $i++) {
            LoginRateLimiter::checkAndHit(self::EMAIL, self::IP);
        }

        $this->travel(61)->seconds();

        $this->assertNull(LoginRateLimiter::checkAndHit(self::EMAIL, self::IP));
    }

    public function test_successful_login_resets_the_attempt_counter(): void
    {
        // Trei încercări eșuate, sub pragul de blocare.
        for ($i = 0; $i < 3; $i++) {
            LoginRateLimiter::checkAndHit(self::EMAIL, self::IP);
        }

        // Login reușit — App\Listeners\ClearLoginRateLimitOnSuccess apelează asta.
        LoginRateLimiter::clear(self::EMAIL, self::IP);

        // Dacă cele 3 încercări anterioare ar fi rămas în contor, a 3-a
        // încercare de mai jos (a 6-a per total) ar bloca imediat.
        for ($i = 0; $i < 5; $i++) {
            $this->assertNull(
                LoginRateLimiter::checkAndHit(self::EMAIL, self::IP),
                "Încercarea #{$i} de după reset nu ar trebui să fie blocată."
            );
        }
    }

    public function test_backoff_escalates_across_repeated_lockouts(): void
    {
        // Prima blocare: 1 minut.
        for ($i = 0; $i < 6; $i++) {
            $seconds = LoginRateLimiter::checkAndHit(self::EMAIL, self::IP);
        }
        $this->assertSame(60, $seconds);

        $this->travel(61)->seconds();

        // A doua blocare (aceeași combinație email+IP): 5 minute.
        for ($i = 0; $i < 6; $i++) {
            $seconds = LoginRateLimiter::checkAndHit(self::EMAIL, self::IP);
        }
        $this->assertSame(5 * 60, $seconds);

        $this->travel(5 * 60 + 1)->seconds();

        // A treia blocare: 15 minute.
        for ($i = 0; $i < 6; $i++) {
            $seconds = LoginRateLimiter::checkAndHit(self::EMAIL, self::IP);
        }
        $this->assertSame(15 * 60, $seconds);

        $this->travel(15 * 60 + 1)->seconds();

        // A patra blocare rămâne plafonată la 15 minute.
        for ($i = 0; $i < 6; $i++) {
            $seconds = LoginRateLimiter::checkAndHit(self::EMAIL, self::IP);
        }
        $this->assertSame(15 * 60, $seconds);
    }

    public function test_rate_limit_is_scoped_per_email_and_ip_combination(): void
    {
        for ($i = 0; $i < 6; $i++) {
            LoginRateLimiter::checkAndHit(self::EMAIL, self::IP);
        }

        $this->assertNotNull(LoginRateLimiter::checkAndHit(self::EMAIL, self::IP));
        $this->assertNull(LoginRateLimiter::checkAndHit('altul@example.com', self::IP));
        $this->assertNull(LoginRateLimiter::checkAndHit(self::EMAIL, '198.51.100.20'));
    }
}
