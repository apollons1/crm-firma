<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OpportunitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'opportunities';

    protected static ?string $title = 'Oportunități';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                Textarea::make('description')
                    ->label('Descriere')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Titlu')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('estimated_value')
                    ->label('Valoare')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('currency')
                    ->label('Monedă'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'won' => 'success',
                        'lost' => 'danger',
                        'negotiation' => 'warning',
                        'proposal' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'lead' => 'Lead',
                        'qualified' => 'Calificat',
                        'proposal' => 'Propunere',
                        'negotiation' => 'Negociere',
                        'won' => 'Câștigat',
                        'lost' => 'Pierdut',
                        default => $state,
                    }),
                TextColumn::make('probability')
                    ->label('Prob. (%)')
                    ->sortable(),
                TextColumn::make('expected_close_date')
                    ->label('Dată închidere')
                    ->date('d.m.Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'lead' => 'Lead',
                        'qualified' => 'Calificat',
                        'proposal' => 'Propunere',
                        'negotiation' => 'Negociere',
                        'won' => 'Câștigat',
                        'lost' => 'Pierdut',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make(),
            ]);
    }
}
