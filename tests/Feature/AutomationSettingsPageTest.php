<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AutomationSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/automation-settings');

        $response->assertRedirect();
    }

    public function test_user_without_super_admin_role_is_forbidden(): void
    {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get('/admin/automation-settings');

        $response->assertForbidden();
    }

    public function test_super_admin_can_access(): void
    {
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get('/admin/automation-settings');

        $response->assertOk();
        $response->assertSee('Oportunitate câștigată');
        $response->assertSee('Oportunitate blocată');
    }
}
