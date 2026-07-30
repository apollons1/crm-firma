<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Verifică regula din AdminPanelProvider: 2FA e obligatoriu pentru
 * super_admin/admin, opțional pentru sales_manager/sales_rep.
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

    private function isRequiredForRole(string $role): bool
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user);

        return Filament::getPanel('admin')->isMultiFactorAuthenticationRequired();
    }

    public function test_is_required_for_super_admin(): void
    {
        $this->assertTrue($this->isRequiredForRole('super_admin'));
    }

    public function test_is_required_for_admin(): void
    {
        $this->assertTrue($this->isRequiredForRole('admin'));
    }

    public function test_is_optional_for_sales_manager(): void
    {
        $this->assertFalse($this->isRequiredForRole('sales_manager'));
    }

    public function test_is_optional_for_sales_rep(): void
    {
        $this->assertFalse($this->isRequiredForRole('sales_rep'));
    }

    public function test_is_not_required_for_guest(): void
    {
        $this->assertFalse(Filament::getPanel('admin')->isMultiFactorAuthenticationRequired());
    }
}
