<?php

namespace Tests\Feature;

use App\Http\Middleware\SetSentryUserContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Sentry\UserDataBag;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Testează App\Http\Middleware\SetSentryUserContext — atașează userul
 * autentificat (id, email, roluri) la scope-ul Sentry curent, astfel încât
 * orice eveniment trimis ulterior în request să fie asociat cu el.
 */
class SetSentryUserContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Scope Sentry curat pentru fiecare test — altfel userul rămâne
        // atașat din testul anterior (scope-ul e stocat static în SDK).
        SentrySdk::init();
    }

    public function test_attaches_id_email_and_roles_to_the_sentry_scope(): void
    {
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create(['email' => 'admin@example.com']);
        $user->assignRole('super_admin');

        $request = Request::create('/admin');
        $request->setUserResolver(fn () => $user);

        (new SetSentryUserContext)->handle($request, fn ($req) => response('ok'));

        $sentryUser = $this->currentSentryUser();

        $this->assertNotNull($sentryUser);
        $this->assertSame($user->id, $sentryUser->getId());
        $this->assertSame('admin@example.com', $sentryUser->getEmail());
        $this->assertSame(['super_admin'], $sentryUser->getMetadata()['roles']);
    }

    public function test_does_not_attach_a_user_when_the_request_is_unauthenticated(): void
    {
        $request = Request::create('/admin/login');
        $request->setUserResolver(fn () => null);

        $response = (new SetSentryUserContext)->handle($request, fn ($req) => response('ok'));

        $this->assertSame('ok', $response->getContent());
        $this->assertNull($this->currentSentryUser());
    }

    private function currentSentryUser(): ?UserDataBag
    {
        $user = null;

        SentrySdk::getCurrentHub()->configureScope(function (Scope $scope) use (&$user): void {
            $user = $scope->getUser();
        });

        return $user;
    }
}
