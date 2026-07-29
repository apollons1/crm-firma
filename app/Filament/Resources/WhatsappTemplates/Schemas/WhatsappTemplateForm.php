<?php

namespace App\Filament\Resources\WhatsappTemplates\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WhatsappTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nume')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->helperText('Nume intern, doar pentru identificare în CRM.'),
                TextInput::make('twilio_content_sid')
                    ->label('Content SID Twilio')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('HXabc123...')
                    ->helperText('Din Twilio Console → Content Template Builder.'),
                Select::make('category')
                    ->label('Categorie')
                    ->options([
                        'marketing' => 'Marketing',
                        'utility' => 'Utility',
                        'authentication' => 'Authentication',
                    ])
                    ->required(),
                TextInput::make('language')
                    ->label('Limbă')
                    ->required()
                    ->default('ro')
                    ->maxLength(10)
                    ->helperText('Codul de limbă folosit în Twilio (ex: ro, en).'),
                Select::make('status')
                    ->label('Status aprobare')
                    ->options([
                        'pending' => 'În așteptare',
                        'approved' => 'Aprobat',
                        'rejected' => 'Respins',
                    ])
                    ->default('pending')
                    ->required()
                    ->helperText('Doar template-urile "Aprobat" pot fi folosite la trimitere.'),
                TextInput::make('variables_count')
                    ->label('Număr de variabile')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required()
                    ->helperText('Câte {{1}}, {{2}}, ... conține template-ul.'),
                Textarea::make('body')
                    ->label('Text (preview)')
                    ->required()
                    ->rows(4)
                    ->helperText('Textul aprobat, cu {{1}}, {{2}} ca placeholder-e — doar pentru afișare/preview în CRM.')
                    ->columnSpanFull(),
            ]);
    }
}
