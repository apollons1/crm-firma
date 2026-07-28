<?php

namespace App\Filament\Resources\WhatsappMessages\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WhatsappMessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['opportunity', 'client']))
            ->columns([
                IconColumn::make('direction')
                    ->label('Direcție')
                    ->icon(fn (string $state): string => match ($state) {
                        'sent' => 'heroicon-o-arrow-up-right',
                        'received' => 'heroicon-o-arrow-down-left',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'primary',
                        'received' => 'info',
                        default => 'gray',
                    })
                    ->tooltip(fn (string $state): string => match ($state) {
                        'sent' => 'Trimis',
                        'received' => 'Primit',
                        default => $state,
                    }),
                TextColumn::make('from_number')
                    ->label('De la')
                    ->formatStateUsing(fn (string $state): string => str_replace('whatsapp:', '', $state))
                    ->searchable(),
                TextColumn::make('to_number')
                    ->label('Către')
                    ->formatStateUsing(fn (string $state): string => str_replace('whatsapp:', '', $state))
                    ->searchable(),
                TextColumn::make('body')
                    ->label('Mesaj')
                    ->limit(50)
                    ->placeholder('(doar media)'),
                TextColumn::make('opportunity.title')
                    ->label('Oportunitate')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'queued' => 'În coadă',
                        'sent' => 'Trimis',
                        'delivered' => 'Livrat',
                        'read' => 'Citit',
                        'received' => 'Primit',
                        'failed' => 'Eșuat',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'queued' => 'gray',
                        'sent' => 'info',
                        'delivered' => 'primary',
                        'read' => 'success',
                        'received' => 'info',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('sent_at')
                    ->label('Trimis la')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('direction')
                    ->label('Direcție')
                    ->options([
                        'sent' => 'Trimis',
                        'received' => 'Primit',
                    ]),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'queued' => 'În coadă',
                        'sent' => 'Trimis',
                        'delivered' => 'Livrat',
                        'read' => 'Citit',
                        'received' => 'Primit',
                        'failed' => 'Eșuat',
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
            ->defaultSort('sent_at', 'desc');
    }
}
