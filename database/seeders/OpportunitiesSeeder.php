<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Seeder;

class OpportunitiesSeeder extends Seeder
{
    private array $titluri = [
        'Implementare CRM',
        'Servicii consultanță Q%s',
        'Audit IT și securitate',
        'Pachet anual marketing digital',
        'Dezvoltare aplicație web',
        'Integrare ERP',
        'Campanie SEO și PPC',
        'Servicii mentenanță software',
        'Proiect automatizare procese',
        'Contract suport tehnic anual',
        'Implementare soluție BI',
        'Migrare infrastructură cloud',
        'Training și workshop echipă',
        'Audit financiar și fiscal',
        'Proiect renovare sediu',
    ];

    // status => [probabilitate, label descriptiv]
    private array $statusConfig = [
        'lead'        => 10,
        'qualified'   => 30,
        'proposal'    => 50,
        'negotiation' => 70,
        'won'         => 100,
        'lost'        => 0,
    ];

    public function run(): void
    {
        $clients  = Client::with('contacts')->get();
        $userIds  = User::pluck('id')->toArray();
        $trimestre = ['Q1', 'Q2', 'Q3', 'Q4'];

        foreach ($clients as $client) {
            $numarOportunitati = rand(0, 3);

            for ($i = 0; $i < $numarOportunitati; $i++) {
                $status      = array_rand($this->statusConfig);
                $probabilitate = $this->statusConfig[$status];
                $moneda      = rand(0, 1) ? 'RON' : 'EUR';
                $valoare     = rand(50, 1000) * 100; // 5000 – 100000

                $titluTemplate = $this->titluri[array_rand($this->titluri)];
                $titlu = str_contains($titluTemplate, '%s')
                    ? sprintf($titluTemplate, $trimestre[array_rand($trimestre)])
                    : $titluTemplate;

                // Data de închidere: între 7 și 180 zile în viitor
                $zileViitor = rand(7, 180);
                $dataInchidere = now()->addDays($zileViitor)->format('Y-m-d');

                // Contact opțional: 70% șanse să aibă contact asociat
                $contactId = null;
                if ($client->contacts->isNotEmpty() && rand(1, 10) <= 7) {
                    $contactId = $client->contacts->random()->id;
                }

                Opportunity::create([
                    'client_id'           => $client->id,
                    'contact_id'          => $contactId,
                    'user_id'             => $userIds[array_rand($userIds)],
                    'title'               => $titlu,
                    'description'         => $this->genereazaDescriere($titlu, $client->name),
                    'estimated_value'     => $valoare,
                    'currency'            => $moneda,
                    'status'              => $status,
                    'probability'         => $probabilitate,
                    'expected_close_date' => $dataInchidere,
                ]);
            }
        }
    }

    private function genereazaDescriere(string $titlu, string $client): string
    {
        return "Oportunitate «{$titlu}» pentru clientul {$client}. "
            . 'Necesită analiză inițială și prezentare ofertă personalizată.';
    }
}
