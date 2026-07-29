<?php

namespace App\Filament\Resources\Opportunities\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Plăți';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->visibleTo(auth()->user()))
            ->columns([
                TextColumn::make('description')
                    ->label('Descriere')
                    ->limit(40),
                TextColumn::make('amount')
                    ->label('Sumă')
                    ->formatStateUsing(fn ($state, $record): string => number_format((float) $state, 2).' '.$record->currency)
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'În așteptare',
                        'paid' => 'Plătit',
                        'failed' => 'Eșuat',
                        'expired' => 'Expirat',
                        'canceled' => 'Anulat',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'paid' => 'success',
                        'failed' => 'danger',
                        'expired' => 'warning',
                        'canceled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('checkout_url')
                    ->label('Link')
                    ->limit(30)
                    ->copyable()
                    ->copyMessage('Link copiat!')
                    ->placeholder('—'),
                TextColumn::make('sentByUser.name')
                    ->label('Trimis de')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Generat la')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label('Plătit la')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
