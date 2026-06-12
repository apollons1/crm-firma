<?php

namespace App\Filament\Resources\Clients\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nume')
                    ->required()
                    ->maxLength(255),
                TextInput::make('cui')
                    ->label('CUI')
                    ->maxLength(50),
                TextInput::make('industry')
                    ->label('Industrie')
                    ->maxLength(255),
                TextInput::make('website')
                    ->label('Website')
                    ->url()
                    ->maxLength(255),
                TextInput::make('employees_count')
                    ->label('Număr angajați')
                    ->numeric()
                    ->minValue(0),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'prospect'  => 'Prospect',
                        'active'    => 'Activ',
                        'inactive'  => 'Inactiv',
                    ])
                    ->default('prospect')
                    ->required(),
                Textarea::make('address')
                    ->label('Adresă')
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label('Notițe')
                    ->columnSpanFull(),
            ]);
    }
}
