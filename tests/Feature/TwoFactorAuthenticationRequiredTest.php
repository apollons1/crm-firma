<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Verifică EnsureRequiredMultiFactorAuthenticationIsEnabled: 2FA e
 * obligatoriu pentru super_admin/admin, opțional pentru
 * sales_manager/sales_rep.
 *
 * Nu testăm asta prin Filament::getPanel('admin')->isMultiFactorAuthenticationRequired()
 * — parametrul isRequired al multiFactorAuthentication() e evaluat o
 * singură dată la boot-ul panoului (înregistrarea rutelor), înainte ca
 * userul să fie autentificat, deci un closure bazat pe rol acolo evaluează
 * mereu false și nu impune nimic niciodată. Impunerea reală se face din
 * middleware-ul propriu, evaluat corect la fiecare cerere.
 */
class TwoFactorAuthenticationRequiredTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super_admin', 'admin', 'sales_manager', 'sales_rep'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_super_admin_without_2fa_is_redirected_to_set_up_page(): void
    {
        $user = User::factory()->create(['password_changed_at' => now()]);
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get('/admin');

        $response->assertRedirect('/admin/configurare-2fa-obligatorie');
    }

    public function test_admin_without_2fa_is_redirected_to_set_up_page(): void
    {
        $user = User::factory()->create(['password_changed_at' => now()]);
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get('/admin');

        $response->assertRedirect('/admin/configurare-2fa-obligatorie');
    }

    public function test_super_admin_with_2fa_configured_is_not_redirected(): void
    {
        $user = User::factory()->create([
            'password_changed_at' => now(),
            'app_authentication_secret' => 'fake-secret-for-tests',
        ]);
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
    }

    public function test_sales_manager_without_2fa_is_not_redirected(): void
    {
        $user = User::factory()->create(['password_changed_at' => now()]);
        $user->assignRole('sales_manager');

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
    }

    public function test_sales_rep_without_2fa_is_not_redirected(): void
    {
        $user = User::factory()->create(['password_changed_at' => now()]);
        $user->assignRole('sales_rep');

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
    }

    public function test_user_without_2fa_can_still_reach_the_set_up_page_itself(): void
    {
        $user = User::factory()->create(['password_changed_at' => now()]);
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get('/admin/configurare-2fa-obligatorie');

        $response->assertOk();
    }

    public function test_user_without_2fa_can_still_log_out(): void
    {
        $user = User::factory()->create(['password_changed_at' => now()]);
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->post('/admin/logout');

        $response->assertStatus(302);
    }
}
