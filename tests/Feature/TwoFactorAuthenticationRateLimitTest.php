<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Testează direct isMultiFactorChallengeRateLimited() (logica proprie de
 * blocare temporară), fără să treacă prin fluxul complet de formular MFA —
 * acela e deja acoperit/testat de Filament însuși. Aici verificăm doar
 * comportamentul custom: 5 încercări într-o fereastră de 10 minute →
 * blocare de 30 de minute, independentă de fereastra inițială de numărare.
 */
class TwoFactorAuthenticationRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private function isRateLimited(User $user): bool
    {
        $instance = Livewire::test(Login::class)->instance();

        $method = new ReflectionMethod($instance, 'isMultiFactorChallengeRateLimited');
        $method->setAccessible(true);

        return $method->invoke($instance, $user);
    }

    public function test_allows_up_to_five_attempts_before_locking(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse($this->isRateLimited($user), "Cererea #{$i} nu ar trebui să fie blocată.");
        }
    }

    public function test_sixth_attempt_within_the_window_is_locked_for_thirty_minutes(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->isRateLimited($user);
        }

        $this->assertTrue($this->isRateLimited($user));

        // Blocarea rămâne activă chiar imediat înainte de expirarea celor 30
        // de minute — nu depinde de fereastra inițială de 10 minute de numărare.
        $this->travel(29)->minutes();
        $this->assertTrue($this->isRateLimited($user));
    }

    public function test_lock_expires_after_thirty_minutes(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->isRateLimited($user);
        }

        $this->travel(31)->minutes();

        $this->assertFalse($this->isRateLimited($user));
    }

    public function test_rate_limit_is_scoped_per_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->isRateLimited($userA);
        }

        $this->assertTrue($this->isRateLimited($userA));
        $this->assertFalse($this->isRateLimited($userB));
    }
}
