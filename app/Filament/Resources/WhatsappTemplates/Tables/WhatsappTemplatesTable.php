<?php

namespace App\Filament\Resources\WhatsappTemplates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WhatsappTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nume')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('body')
                    ->label('Text')
                    ->limit(50),
                TextColumn::make('category')
                    ->label('Categorie')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'marketing' => 'Marketing',
                        'utility' => 'Utility',
                        'authentication' => 'Authentication',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'În așteptare',
                        'approved' => 'Aprobat',
                        'rejected' => 'Respins',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('language')
                    ->label('Limbă')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Categorie')
                    ->options([
                        'marketing' => 'Marketing',
                        'utility' => 'Utility',
                        'authentication' => 'Authentication',
                    ]),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'În așteptare',
                        'approved' => 'Aprobat',
                        'rejected' => 'Respins',
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
