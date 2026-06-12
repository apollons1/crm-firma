<?php

namespace Database\Seeders;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Useri de test cu roluri definite.
     * Seeder-ul e idempotent: firstOrCreate nu creează duplicate la re-rulare.
     */
    private array $users = [
        [
            'name'     => 'Manager Vânzări',
            'email'    => 'manager@test.ro',
            'password' => 'password',
            'role'     => 'sales_manager',
        ],
        [
            'name'     => 'Andrei Popescu',
            'email'    => 'andrei@test.ro',
            'password' => 'password',
            'role'     => 'sales_rep',
        ],
        [
            'name'     => 'Maria Ionescu',
            'email'    => 'maria@test.ro',
            'password' => 'password',
            'role'     => 'sales_rep',
        ],
    ];

    public function run(): void
    {
        // ── Creare useri ──────────────────────────────────────────────────────
        foreach ($this->users as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'     => $data['name'],
                    'password' => Hash::make($data['password']),
                ],
            );

            // Sincronizăm rolul (nu adăugăm duplicate dacă deja are rolul)
            if (! $user->hasRole($data['role'])) {
                $user->syncRoles([$data['role']]);
            }

            $this->command->line("  <info>✓</info> {$data['email']} → {$data['role']}");
        }

        // ── Redistribuire oportunități către sales_rep-uri ────────────────────
        // Luăm 10 oportunități aleatorii și le împărțim 5-5 între Andrei și Maria.
        $andrei = User::where('email', 'andrei@test.ro')->first();
        $maria  = User::where('email', 'maria@test.ro')->first();

        if ($andrei && $maria) {
            $opportunities = Opportunity::inRandomOrder()->limit(10)->get();

            $opportunities->chunk(5)->each(function ($chunk, int $index) use ($andrei, $maria) {
                $owner = $index === 0 ? $andrei : $maria;
                $chunk->each(fn (Opportunity $opp) => $opp->update(['user_id' => $owner->id]));
            });

            $this->command->line('');
            $this->command->line("  <info>✓</info> Oportunități redistribuite:");
            $this->command->line("     Andrei Popescu  : 5 oportunități");
            $this->command->line("     Maria Ionescu   : 5 oportunități");
            $this->command->line("     Restul (8)      : rămân la userii existenți");
        }
    }
}
