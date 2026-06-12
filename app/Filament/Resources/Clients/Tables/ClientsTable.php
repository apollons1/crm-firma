<?php

namespace App\Filament\Resources\Clients\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nume')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('cui')
                    ->label('CUI')
                    ->searchable(),
                TextColumn::make('industry')
                    ->label('Industrie')
                    ->sortable(),
                TextColumn::make('employees_count')
                    ->label('Angajați')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active'   => 'success',
                        'inactive' => 'danger',
                        default    => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'prospect' => 'Prospect',
                        'active'   => 'Activ',
                        'inactive' => 'Inactiv',
                        default    => $state,
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Creat la')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'prospect' => 'Prospect',
                        'active'   => 'Activ',
                        'inactive' => 'Inactiv',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
