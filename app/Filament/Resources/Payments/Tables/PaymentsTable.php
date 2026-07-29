<?php

namespace App\Filament\Resources\Payments\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['opportunity', 'client']))
            ->columns([
                TextColumn::make('description')
                    ->label('Descriere')
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable(),
                TextColumn::make('opportunity.title')
                    ->label('Oportunitate')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->copyableState(fn ($record): ?string => $record->checkout_url)
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
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'În așteptare',
                        'paid' => 'Plătit',
                        'failed' => 'Eșuat',
                        'expired' => 'Expirat',
                        'canceled' => 'Anulat',
                    ]),
                SelectFilter::make('opportunity_id')
                    ->label('Oportunitate')
                    ->relationship('opportunity', 'title')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('client_id')
                    ->label('Client')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
