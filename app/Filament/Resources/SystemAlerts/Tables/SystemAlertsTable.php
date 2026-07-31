<?php

namespace App\Filament\Resources\SystemAlerts\Tables;

use App\Models\SystemAlert;
use Filament\Actions\Action;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SystemAlertsTable
{
    private const TYPE_LABELS = [
        'downtime' => 'Downtime',
        'high_cpu' => 'CPU ridicat',
        'failed_backup' => 'Backup eșuat',
        'security_threat' => 'Amenințare securitate',
    ];

    private const SEVERITY_LABELS = [
        'info' => 'Info',
        'warning' => 'Avertisment',
        'critical' => 'Critic',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Tip')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::TYPE_LABELS[$state] ?? $state),
                TextColumn::make('severity')
                    ->label('Severitate')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::SEVERITY_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'warning' => 'warning',
                        'info' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('message')
                    ->label('Mesaj')
                    ->limit(60)
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (SystemAlert $record): string => $record->isResolved() ? 'Rezolvată' : 'Activă')
                    ->color(fn (SystemAlert $record): string => $record->isResolved() ? 'success' : 'danger'),
                TextColumn::make('triggered_at')
                    ->label('Declanșată la')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('resolved_at')
                    ->label('Rezolvată la')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tip')
                    ->options(self::TYPE_LABELS),
                SelectFilter::make('severity')
                    ->label('Severitate')
                    ->options(self::SEVERITY_LABELS),
                TernaryFilter::make('resolved_at')
                    ->label('Status')
                    ->nullable()
                    ->placeholder('Toate')
                    ->trueLabel('Rezolvate')
                    ->falseLabel('Active'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('Vezi')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Detalii alertă')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Închide')
                    ->schema([
                        TextEntry::make('message')
                            ->label('Mesaj')
                            ->columnSpanFull(),
                        TextEntry::make('triggered_at')
                            ->label('Declanșată la')
                            ->dateTime('d.m.Y H:i:s'),
                        TextEntry::make('resolved_at')
                            ->label('Rezolvată la')
                            ->dateTime('d.m.Y H:i:s')
                            ->placeholder('—'),
                        KeyValueEntry::make('metadata')
                            ->label('Detalii tehnice')
                            ->visible(fn (SystemAlert $record): bool => filled($record->metadata))
                            ->columnSpanFull(),
                    ]),
            ])
            ->defaultSort('triggered_at', 'desc');
    }
}
