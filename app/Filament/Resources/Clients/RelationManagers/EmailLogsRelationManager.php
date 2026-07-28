<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Models\EmailLog;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmailLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'emailLogs';

    protected static ?string $title = 'Email-uri';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->visibleTo(auth()->user())->with(['sentBy', 'opportunity']))
            ->columns([
                TextColumn::make('sent_at')
                    ->label('Trimis la')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('to')
                    ->label('Către')
                    ->searchable(),
                TextColumn::make('subject')
                    ->label('Subiect')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'sent' => 'Trimis',
                        'received' => 'Primit',
                        'failed' => 'Eșuat',
                        'pending' => 'În așteptare',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'received' => 'info',
                        'failed' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('opportunity.title')
                    ->label('Oportunitate')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sentBy.name')
                    ->label('Trimis de'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('Vezi')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Detalii email')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Închide')
                    ->schema([
                        TextEntry::make('to')
                            ->label('Către'),
                        TextEntry::make('cc')
                            ->label('CC')
                            ->visible(fn (EmailLog $record): bool => filled($record->cc)),
                        TextEntry::make('subject')
                            ->label('Subiect'),
                        TextEntry::make('error_message')
                            ->label('Eroare')
                            ->visible(fn (EmailLog $record): bool => filled($record->error_message))
                            ->color('danger'),
                        TextEntry::make('body')
                            ->label('Mesaj')
                            ->html()
                            ->columnSpanFull(),
                    ]),
            ])
            ->defaultSort('sent_at', 'desc');
    }
}
