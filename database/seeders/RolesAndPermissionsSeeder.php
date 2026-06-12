<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Permisiuni per resursă ─────────────────────────────────────────────
        $clientPerms      = $this->permsFor('Client');
        $contactPerms     = $this->permsFor('Contact');
        $opportunityPerms = $this->permsFor('Opportunity');
        $rolePerms        = $this->permsFor('Role');
        $userPerms        = $this->permsFor('User');

        // Permisiuni pentru widget-uri și pagini (View:*)
        $widgetPerms = [
            'View:Analize',
            'View:BlockedOpportunitiesStats',
            'View:CrmStatsOverview',
            'View:OpportunitiesDonutChart',
            'View:OpportunitiesLineChart',
            'View:PipelineBarChart',
            'View:TopOpportunitiesTable',
        ];

        // ── 1. super_admin — toate permisiunile ────────────────────────────────
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->syncPermissions(Permission::all());

        // ── 2. admin — toate permisiunile ─────────────────────────────────────
        // Restricțiile fine (nu poate schimba rolul unui super_admin) se impun
        // prin UserPolicy, nu prin permisiuni Spatie.
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());

        // ── 3. sales_manager ──────────────────────────────────────────────────
        // • CRUD complet pe Clients, Contacts, Opportunities
        // • Vede lista utilizatorilor (colegii), dar NU poate modifica useri
        // • Vede dashboard (widgets + pagini)
        // • NU are acces la Roles/Permissions
        $salesManager = Role::firstOrCreate(['name' => 'sales_manager']);
        $salesManager->syncPermissions(array_merge(
            $clientPerms,
            $contactPerms,
            $opportunityPerms,
            ['ViewAny:User', 'View:User'],   // vede colegii, fără editare
            $widgetPerms,
        ));

        // ── 4. sales_rep ──────────────────────────────────────────────────────
        // • View + Create + Update pe Clients și Contacts (fără Delete)
        // • ViewAny + View + Create + Update pe Opportunities
        //   (editare doar pe ale sale — impus prin OpportunityPolicy)
        // • Vede dashboard (widgets)
        // • NU vede Roles, Permissions, Users
        $salesRep = Role::firstOrCreate(['name' => 'sales_rep']);
        $salesRep->syncPermissions(array_merge(
            ['ViewAny:Client',      'View:Client',      'Create:Client',      'Update:Client'],
            ['ViewAny:Contact',     'View:Contact',     'Create:Contact',     'Update:Contact'],
            ['ViewAny:Opportunity', 'View:Opportunity', 'Create:Opportunity', 'Update:Opportunity'],
            $widgetPerms,
        ));

        // ── Atribuire rol super_admin la admin@test.ro ────────────────────────
        $adminUser = User::where('email', 'admin@test.ro')->first();
        if ($adminUser && ! $adminUser->hasRole('super_admin')) {
            $adminUser->assignRole('super_admin');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('Roluri actualizate: super_admin, admin, sales_manager, sales_rep');
        $this->command->info('admin@test.ro → super_admin: ' . (
            $adminUser?->hasRole('super_admin') ? 'OK' : 'userul nu a fost găsit'
        ));

        $this->command->table(
            ['Rol', 'Permisiuni'],
            Role::withCount('permissions')->get()->map(fn ($r) => [$r->name, $r->permissions_count])->toArray()
        );
    }

    private function permsFor(string $resource): array
    {
        return Permission::where('name', 'like', '%:' . $resource)
            ->pluck('name')
            ->toArray();
    }
}
