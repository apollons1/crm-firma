<?php

namespace Tests\Feature;

use App\Listeners\RecordFailedLoginAttempt;
use App\Models\FailedLoginAttempt;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testează App\Listeners\RecordFailedLoginAttempt — listener-ul ascultă
 * evenimentul Illuminate\Auth\Events\Failed (declanșat de Filament la orice
 * autentificare eșuată) și salvează o urmă în failed_login_attempts, fără
 * să stocheze niciodată parola introdusă.
 */
class RecordFailedLoginAttemptTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_ip_email_and_user_agent_on_failed_login(): void
    {
        $this->withHeaders(['User-Agent' => 'TestAgent/1.0'])->get('/');

        $event = new Failed('web', null, [
            'email' => 'atacator@example.com',
            'password' => 'parola-super-secreta',
        ]);

        (new RecordFailedLoginAttempt)->handle($event);

        $this->assertDatabaseHas('failed_login_attempts', [
            'email_attempted' => 'atacator@example.com',
        ]);

        $attempt = FailedLoginAttempt::sole();
        $this->assertNotNull($attempt->ip_address);
        $this->assertNotNull($attempt->attempted_at);
    }

    public function test_never_stores_the_submitted_password(): void
    {
        $event = new Failed('web', null, [
            'email' => 'atacator@example.com',
            'password' => 'parola-super-secreta',
        ]);

        (new RecordFailedLoginAttempt)->handle($event);

        $attempt = FailedLoginAttempt::sole();

        $this->assertStringNotContainsString('parola-super-secreta', json_encode($attempt->getAttributes()));
        $this->assertArrayNotHasKey('password', $attempt->getAttributes());
    }

    public function test_falls_back_to_the_authenticated_users_email_when_credentials_array_lacks_one(): void
    {
        $user = User::factory()->create(['email' => 'stiut@example.com']);

        $event = new Failed('web', $user, ['password' => 'orice']);

        (new RecordFailedLoginAttempt)->handle($event);

        $this->assertDatabaseHas('failed_login_attempts', [
            'email_attempted' => 'stiut@example.com',
        ]);
    }
}
