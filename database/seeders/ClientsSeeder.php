<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientsSeeder extends Seeder
{
    public function run(): void
    {
        $clients = [
            [
                'name'     => 'LogisTrans SRL',
                'cui'      => 'RO14872345',
                'address'  => 'Str. Industriilor nr. 45, București, Sector 3',
                'industry' => 'Logistică și transport',
                'website'  => 'https://www.logistrans.ro',
                'notes'    => 'Client vechi, interesat de extinderea flotei.',
                'status'   => 'active',
            ],
            [
                'name'     => 'Marketing Pro Solutions SRL',
                'cui'      => 'RO28341209',
                'address'  => 'Calea Victoriei nr. 120, București, Sector 1',
                'industry' => 'Marketing și publicitate',
                'website'  => 'https://www.marketingpro.ro',
                'notes'    => 'Agenție de marketing cu portofoliu enterprise.',
                'status'   => 'active',
            ],
            [
                'name'     => 'TechBucharest IT SRL',
                'cui'      => 'RO36912078',
                'address'  => 'Bd. Dimitrie Pompeiu nr. 9-9A, București, Sector 2',
                'industry' => 'IT și software',
                'website'  => 'https://www.techbucharest.ro',
                'notes'    => 'Startup IT cu creștere rapidă, caută soluții CRM.',
                'status'   => 'prospect',
            ],
            [
                'name'     => 'Construct Solid SA',
                'cui'      => 'RO10234567',
                'address'  => 'Str. Traian Vuia nr. 8, Cluj-Napoca, Cluj',
                'industry' => 'Construcții',
                'website'  => 'https://www.constructsolid.ro',
                'notes'    => 'Companie de construcții civile și industriale.',
                'status'   => 'active',
            ],
            [
                'name'     => 'RetailMax România SRL',
                'cui'      => 'RO22198734',
                'address'  => 'Str. Memorandumului nr. 28, Cluj-Napoca, Cluj',
                'industry' => 'Retail',
                'website'  => 'https://www.retailmax.ro',
                'notes'    => 'Lanț de magazine, interesat de soluții B2B.',
                'status'   => 'prospect',
            ],
            [
                'name'     => 'MedFarm Grup SRL',
                'cui'      => 'RO31456789',
                'address'  => 'Bd. Revoluției nr. 56, Timișoara, Timiș',
                'industry' => 'Farmacie și sănătate',
                'website'  => 'https://www.medfarm.ro',
                'notes'    => 'Distribuitor de medicamente și echipamente medicale.',
                'status'   => 'active',
            ],
            [
                'name'     => 'AgroVest Est SRL',
                'cui'      => 'RO17823401',
                'address'  => 'Str. Independenței nr. 14, Iași, Iași',
                'industry' => 'Agricultură',
                'website'  => 'https://www.agrovest.ro',
                'notes'    => 'Producător agricol, interesat de CRM pentru distribuție.',
                'status'   => 'inactive',
            ],
            [
                'name'     => 'EnergyPlus Solutions SRL',
                'cui'      => 'RO25671234',
                'address'  => 'Str. Libertății nr. 3, Timișoara, Timiș',
                'industry' => 'Energie și utilități',
                'website'  => 'https://www.energyplus.ro',
                'notes'    => 'Furnizor de soluții fotovoltaice și energie verde.',
                'status'   => 'prospect',
            ],
            [
                'name'     => 'FinConsult Iași SRL',
                'cui'      => 'RO40123890',
                'address'  => 'Bd. Carol I nr. 33, Iași, Iași',
                'industry' => 'Consultanță financiară',
                'website'  => 'https://www.finconsult-iasi.ro',
                'notes'    => 'Birou de contabilitate și consultanță fiscală.',
                'status'   => 'active',
            ],
            [
                'name'     => 'SmartHome Domotică SRL',
                'cui'      => 'RO33987651',
                'address'  => 'Calea Florești nr. 77, Cluj-Napoca, Cluj',
                'industry' => 'IT și automatizări',
                'website'  => 'https://www.smarthome-ro.ro',
                'notes'    => 'Soluții domotică și sisteme de securitate.',
                'status'   => 'inactive',
            ],
        ];

        foreach ($clients as $client) {
            Client::create($client);
        }
    }
}
