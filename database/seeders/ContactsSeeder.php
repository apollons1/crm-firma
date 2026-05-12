<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Contact;
use Illuminate\Database\Seeder;

class ContactsSeeder extends Seeder
{
    private array $prenume = [
        'Alexandru', 'Andrei', 'Bogdan', 'Cătălin', 'Cristian',
        'Daniel', 'Florin', 'Gabriel', 'Ioan', 'Marius',
        'Mihai', 'Nicolae', 'Radu', 'Răzvan', 'Ștefan',
        'Alina', 'Ana', 'Bianca', 'Cristina', 'Diana',
        'Elena', 'Ioana', 'Laura', 'Maria', 'Monica',
        'Raluca', 'Roxana', 'Simona', 'Teodora', 'Valentina',
    ];

    private array $nume = [
        'Andrei', 'Barbu', 'Ciobanu', 'Constantin', 'Dima',
        'Dumitrescu', 'Florescu', 'Gheorghiu', 'Ionescu', 'Luca',
        'Marin', 'Matei', 'Moldovan', 'Nistor', 'Popa',
        'Popescu', 'Radu', 'Stan', 'Stoica', 'Tudor',
        'Ungureanu', 'Vasile', 'Vlad', 'Zamfir', 'Dragomir',
    ];

    private array $functii = [
        'CEO',
        'Director General',
        'Director Comercial',
        'Director de Vânzări',
        'Director Marketing',
        'Manager Achiziții',
        'Account Manager',
        'Marketing Manager',
        'Business Development Manager',
        'Office Manager',
    ];

    public function run(): void
    {
        $clients = Client::all();

        foreach ($clients as $client) {
            $domeniu = $this->extrageDomeniu($client->website);
            $numarContacte = rand(1, 4);

            for ($i = 0; $i < $numarContacte; $i++) {
                $prenume = $this->prenume[array_rand($this->prenume)];
                $nume    = $this->nume[array_rand($this->nume)];
                $functie = $this->functii[array_rand($this->functii)];
                $slug    = strtolower(
                    $this->translitereaza($prenume) . '.' . $this->translitereaza($nume)
                );

                Contact::create([
                    'client_id'  => $client->id,
                    'first_name' => $prenume,
                    'last_name'  => $nume,
                    'position'   => $functie,
                    'email'      => $slug . '@' . $domeniu,
                    'phone'      => $this->genereazaTelefon(),
                    'notes'      => $i === 0 ? 'Contact principal al firmei.' : null,
                ]);
            }
        }
    }

    private function extrageDomeniu(string $website): string
    {
        $host = parse_url($website, PHP_URL_HOST) ?? 'firma.ro';
        return ltrim($host, 'www.');
    }

    private function translitereaza(string $text): string
    {
        $map = [
            'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ț' => 't',
            'Ă' => 'a', 'Â' => 'a', 'Î' => 'i', 'Ș' => 's', 'Ț' => 't',
            'ş' => 's', 'ţ' => 't', 'Ş' => 's', 'Ţ' => 't',
        ];
        return strtr($text, $map);
    }

    private function genereazaTelefon(): string
    {
        $prefixe = ['072', '073', '074', '075', '076', '077', '078'];
        $prefix  = $prefixe[array_rand($prefixe)];
        return $prefix . ' ' . rand(100, 999) . ' ' . rand(100, 999);
    }
}
