<?php

namespace Tests\Feature;

use App\Models\GoogleToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GmailSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/gmail-settings');

        $response->assertRedirect();
    }

    public function test_user_without_admin_role_is_forbidden(): void
    {
        Role::create(['name' => 'sales_rep', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('sales_rep');

        $response = $this->actingAs($user)->get('/admin/gmail-settings');

        $response->assertForbidden();
    }

    public function test_admin_sees_connected_account(): void
    {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);

        // admin are 2FA obligatoriu (EnsureRequiredMultiFactorAuthenticationIsEnabled).
        $user = User::factory()->create(['app_authentication_secret' => 'fake-secret-for-tests']);
        $user->assignRole('admin');

        GoogleToken::create([
            'email' => 'aktivtherm@gmail.com',
            'access_token' => 'fake-access-token',
            'refresh_token' => 'fake-refresh-token',
            'expires_at' => now()->addHour(),
            'scopes' => ['https://www.googleapis.com/auth/gmail.send'],
        ]);

        $response = $this->actingAs($user)->get('/admin/gmail-settings');

        $response->assertOk();
        $response->assertSee('aktivtherm@gmail.com');
    }

    public function test_super_admin_sees_no_account_connected_state(): void
    {
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::factory()->create(['app_authentication_secret' => 'fake-secret-for-tests']);
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get('/admin/gmail-settings');

        $response->assertOk();
        $response->assertSee('Niciun cont Gmail conectat');
    }
}
