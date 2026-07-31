<?php

namespace Tests\Feature;

use App\Filament\Resources\SystemAlerts\Pages\ListSystemAlerts;
use App\Models\SystemAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemAlertResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $this->actingAs($admin);
    }

    public function test_super_admin_can_view_the_system_alerts_list(): void
    {
        $alert = SystemAlert::create([
            'type' => 'downtime',
            'severity' => 'critical',
            'message' => 'CRM AktivTherm nu răspunde.',
            'triggered_at' => now(),
        ]);

        Livewire::test(ListSystemAlerts::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$alert]);
    }

    public function test_the_list_can_be_filtered_by_status(): void
    {
        $active = SystemAlert::create([
            'type' => 'downtime',
            'severity' => 'critical',
            'message' => 'Activă',
            'triggered_at' => now(),
        ]);

        $resolved = SystemAlert::create([
            'type' => 'downtime',
            'severity' => 'critical',
            'message' => 'Rezolvată',
            'triggered_at' => now()->subHour(),
            'resolved_at' => now(),
        ]);

        Livewire::test(ListSystemAlerts::class)
            ->filterTable('resolved_at', false)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$resolved]);
    }
}
