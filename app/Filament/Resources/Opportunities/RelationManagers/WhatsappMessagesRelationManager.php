<?php

namespace App\Filament\Resources\Opportunities\RelationManagers;

use App\Models\WhatsappMessage;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WhatsappMessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'whatsappMessages';

    protected static ?string $title = 'WhatsApp';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->visibleTo(auth()->user())->with('sentByUser'))
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
                    }),
                TextColumn::make('sent_at')
                    ->label('Trimis la')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('body')
                    ->label('Mesaj')
                    ->limit(50)
                    ->placeholder('(doar media)'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'queued' => 'În coadă',
                        'sent' => 'Trimis',
                        'delivered' => 'Livrat',
                        'read' => 'Citit',
                        'failed' => 'Eșuat',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'queued' => 'gray',
                        'sent' => 'info',
                        'delivered' => 'primary',
                        'read' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('sentByUser.name')
                    ->label('Trimis de')
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('Vezi')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Detalii mesaj WhatsApp')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Închide')
                    ->schema([
                        TextEntry::make('from_number')
                            ->label('De la'),
                        TextEntry::make('to_number')
                            ->label('Către'),
                        TextEntry::make('body')
                            ->label('Mesaj')
                            ->placeholder('(doar media)')
                            ->columnSpanFull(),
                        TextEntry::make('media_url')
                            ->label('Atașament')
                            ->url(fn (WhatsappMessage $record): ?string => $record->media_url)
                            ->openUrlInNewTab()
                            ->visible(fn (WhatsappMessage $record): bool => filled($record->media_url)),
                        TextEntry::make('error_message')
                            ->label('Eroare')
                            ->color('danger')
                            ->visible(fn (WhatsappMessage $record): bool => filled($record->error_message))
                            ->columnSpanFull(),
                    ]),
            ])
            ->defaultSort('sent_at', 'desc');
    }
}
