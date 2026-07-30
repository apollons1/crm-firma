<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EditProfileSecurityTabTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/profile');

        $response->assertRedirect();
    }

    public function test_profile_page_shows_securitate_tab(): void
    {
        Role::create(['name' => 'sales_rep', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('sales_rep');

        $response = $this->actingAs($user)->get('/admin/profile');

        $response->assertOk();
        $response->assertSee('Securitate');
        $response->assertSee('Date cont');
    }
}
