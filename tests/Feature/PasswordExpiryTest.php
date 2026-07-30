<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\ChangeExpiredPassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PasswordExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);

        foreach (['super_admin', 'admin', 'sales_manager', 'sales_rep'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_super_admin_with_expired_password_is_redirected_to_change_password_page(): void
    {
        $user = User::factory()->create(['password_changed_at' => now()->subDays(91)]);
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get('/admin');

        $response->assertRedirect('/admin/parola-expirata');
    }

    public function test_admin_with_null_password_changed_at_is_redirected(): void
    {
        // Conturi create înainte de politica de expirare — password_changed_at
        // nu a fost niciodată populat, deci tratăm ca expirat.
        $user = User::factory()->create();
        $user->forceFill(['password_changed_at' => null])->save();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get('/admin');

        $response->assertRedirect('/admin/parola-expirata');
    }

    public function test_super_admin_with_recent_password_is_not_redirected(): void
    {
        $user = User::factory()->create(['password_changed_at' => now()->subDays(10)]);
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
    }

    public function test_sales_rep_with_expired_password_is_not_redirected(): void
    {
        $user = User::factory()->create(['password_changed_at' => now()->subDays(200)]);
        $user->assignRole('sales_rep');

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
    }

    public function test_sales_manager_with_expired_password_is_not_redirected(): void
    {
        $user = User::factory()->create(['password_changed_at' => now()->subDays(200)]);
        $user->assignRole('sales_manager');

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
    }

    public function test_expired_user_can_still_reach_the_change_password_page_itself(): void
    {
        $user = User::factory()->create(['password_changed_at' => now()->subDays(91)]);
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get('/admin/parola-expirata');

        $response->assertOk();
    }

    public function test_expired_user_can_still_log_out(): void
    {
        $user = User::factory()->create(['password_changed_at' => now()->subDays(91)]);
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->post('/admin/logout');

        $response->assertRedirect();
        $response->assertStatus(302);
    }

    public function test_changing_password_on_forced_page_clears_expiry_and_allows_access(): void
    {
        $user = User::factory()->create(['password_changed_at' => now()->subDays(91)]);
        $user->assignRole('super_admin');

        $this->actingAs($user)
            ->get('/admin/parola-expirata')
            ->assertOk();

        Livewire::actingAs($user)
            ->test(ChangeExpiredPassword::class)
            ->fillForm([
                'password' => 'BrandNewStr0ng!99',
                'passwordConfirmation' => 'BrandNewStr0ng!99',
            ])
            ->call('changePassword')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertTrue(Hash::check('BrandNewStr0ng!99', $user->password));
        $this->assertTrue($user->password_changed_at->greaterThan(now()->subMinute()));
    }
}
