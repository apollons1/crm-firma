<?php

namespace App\Filament\Resources\Opportunities\Schemas;

use App\Models\Contact;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class OpportunityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('client_id')
                    ->label('Client')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                Select::make('contact_id')
                    ->label('Contact')
                    ->options(function (callable $get) {
                        $clientId = $get('client_id');
                        if (!$clientId) {
                            return Contact::query()
                                ->selectRaw("id, CONCAT(first_name, ' ', last_name) as full_name")
                                ->pluck('full_name', 'id');
                        }
                        return Contact::where('client_id', $clientId)
                            ->selectRaw("id, CONCAT(first_name, ' ', last_name) as full_name")
                            ->pluck('full_name', 'id');
                    })
                    ->searchable()
                    ->nullable(),
                TextInput::make('title')
                    ->label('Titlu')
                    ->required()
                    ->maxLength(255),
                TextInput::make('estimated_value')
                    ->label('Valoare estimată')
                    ->numeric()
                    ->minValue(0),
                Select::make('currency')
                    ->label('Monedă')
                    ->options(['RON' => 'RON', 'EUR' => 'EUR', 'USD' => 'USD'])
                    ->default('RON')
                    ->required(),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'lead' => 'Lead',
                        'qualified' => 'Calificat',
                        'proposal' => 'Propunere',
                        'negotiation' => 'Negociere',
                        'won' => 'Câștigat',
                        'lost' => 'Pierdut',
                    ])
                    ->default('lead')
                    ->required(),
                TextInput::make('probability')
                    ->label('Probabilitate (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(0),
                DatePicker::make('expected_close_date')
                    ->label('Dată estimată închidere')
                    ->displayFormat('d.m.Y'),
                Select::make('user_id')
                    ->label('Responsabil')
                    ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                    ->default(fn () => auth()->id())
                    ->searchable()
                    ->required(),
                Select::make('lead_source')
                    ->label('Sursă lead')
                    ->options([
                        'website'        => 'Website',
                        'recommendation' => 'Recomandare',
                        'cold_call'      => 'Apel rece',
                        'event'          => 'Eveniment',
                        'social_media'   => 'Social media',
                        'other'          => 'Altele',
                    ])
                    ->placeholder('Selectează sursa')
                    ->nullable(),
                Textarea::make('description')
                    ->label('Descriere')
                    ->columnSpanFull(),
            ]);
    }
}
