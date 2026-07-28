<?php

namespace App\Filament\Resources\EmailLogs\Tables;

use App\Models\EmailAttachment;
use App\Models\EmailLog;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmailLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['gmailAccount', 'sentBy', 'opportunity', 'client', 'attachments']))
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
                TextColumn::make('direction')
                    ->label('Direcție')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'sent' => 'Trimis',
                        'received' => 'Primit',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'primary',
                        'received' => 'info',
                        default => 'gray',
                    }),
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
                TextColumn::make('gmailAccount.email')
                    ->label('Cont Gmail')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sentBy.name')
                    ->label('Trimis de'),
                TextColumn::make('opportunity.title')
                    ->label('Oportunitate')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('client.name')
                    ->label('Client')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                        'sent' => 'Trimis',
                        'received' => 'Primit',
                        'failed' => 'Eșuat',
                        'pending' => 'În așteptare',
                    ]),
                SelectFilter::make('gmail_account')
                    ->label('Cont Gmail')
                    ->relationship('gmailAccount', 'email'),
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
                        RepeatableEntry::make('attachments')
                            ->label('Atașamente')
                            ->schema([
                                TextEntry::make('filename')
                                    ->hiddenLabel()
                                    ->icon('heroicon-o-paper-clip')
                                    ->formatStateUsing(fn (EmailAttachment $record): string => "{$record->filename} ({$record->formattedSize()})")
                                    ->url(fn (EmailAttachment $record): string => route('email-attachments.download', $record))
                                    ->openUrlInNewTab(),
                            ])
                            ->visible(fn (EmailLog $record): bool => $record->attachments->isNotEmpty())
                            ->columnSpanFull(),
                    ]),
            ])
            ->defaultSort('sent_at', 'desc');
    }
}
