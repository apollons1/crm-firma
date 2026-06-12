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
        // Resetăm cache-ul Spatie înainte de orice modificare
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Permisiuni per resursă ─────────────────────────────────────────────
        $clientPerms      = $this->permsFor('Client');
        $contactPerms     = $this->permsFor('Contact');
        $opportunityPerms = $this->permsFor('Opportunity');
        $rolePerms        = $this->permsFor('Role');

        // Permisiuni pentru widget-uri și pagini (doar View:*)
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
        // Restricțiile (nu poate atribui super_admin, nu poate șterge super_admin)
        // se impun prin politici (Policies), nu prin permisiuni Spatie.
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());

        // ── 3. sales_manager ──────────────────────────────────────────────────
        // • CRUD complet pe Clients, Contacts, Opportunities
        // • Vede dashboard (widgets + pagini)
        // • NU are acces la Roles/Permissions
        // Notă: nu există UserResource în Filament, deci nu există permisiuni
        // pentru User. Accesul la lista de utilizatori va fi configurat când
        // se va adăuga un UserResource dedicat (task viitor).
        $salesManager = Role::firstOrCreate(['name' => 'sales_manager']);
        $salesManager->syncPermissions(array_merge(
            $clientPerms,
            $contactPerms,
            $opportunityPerms,
            $widgetPerms,
        ));

        // ── 4. sales_rep ──────────────────────────────────────────────────────
        // • View + Create + Update pe Clients și Contacts (fără Delete)
        // • ViewAny + View + Create pe Opportunities
        //   (Update doar pe oportunități proprii — impus prin Policy la task 10.8)
        // • Vede dashboard (widgets)
        // • NU are acces la Roles/Permissions/Users
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

        // Resetăm cache-ul din nou după modificări
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('Roluri create: super_admin, admin, sales_manager, sales_rep');
        $this->command->info('admin@test.ro → super_admin: ' . (
            $adminUser?->hasRole('super_admin') ? 'OK' : 'userul nu a fost găsit'
        ));
    }

    /**
     * Returnează toate permisiunile Shield generate pentru o resursă dată.
     * Exemplu: permsFor('Client') → ['Create:Client', 'Delete:Client', ...]
     */
    private function permsFor(string $resource): array
    {
        return Permission::where('name', 'like', '%:' . $resource)
            ->pluck('name')
            ->toArray();
    }
}
