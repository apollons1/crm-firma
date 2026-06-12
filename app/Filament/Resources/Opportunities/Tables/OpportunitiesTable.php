<?php

namespace App\Filament\Resources\Opportunities\Tables;

use App\Models\Opportunity;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OpportunitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Titlu')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Responsabil')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('estimated_value')
                    ->label('Valoare estimată')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('currency')
                    ->label('Monedă'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'won'         => 'success',
                        'lost'        => 'danger',
                        'negotiation' => 'warning',
                        'proposal'    => 'info',
                        'qualified'   => 'primary',
                        default       => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'lead'        => 'Lead',
                        'qualified'   => 'Calificat',
                        'proposal'    => 'Propunere',
                        'negotiation' => 'Negociere',
                        'won'         => 'Câștigat',
                        'lost'        => 'Pierdut',
                        default       => $state,
                    })
                    ->sortable(),
                TextColumn::make('probability')
                    ->label('Prob. (%)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('expected_close_date')
                    ->label('Dată închidere')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Creat la')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'lead'        => 'Lead',
                        'qualified'   => 'Calificat',
                        'proposal'    => 'Propunere',
                        'negotiation' => 'Negociere',
                        'won'         => 'Câștigat',
                        'lost'        => 'Pierdut',
                    ]),
                SelectFilter::make('client_id')
                    ->label('Client')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('user_id')
                    ->label('Responsabil')
                    ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload(),
                Filter::make('my_opportunities')
                    ->label('Doar ale mele')
                    ->query(fn (Builder $query) => $query->where('user_id', auth()->id()))
                    ->toggle(),
            ])
            ->recordActions([
                Action::make('markWon')
                    ->label('Câștigată')
                    ->icon('heroicon-o-trophy')
                    ->color('success')
                    ->visible(fn (Opportunity $record): bool => !in_array($record->status, ['won', 'lost']))
                    ->action(function (Opportunity $record): void {
                        $record->update(['status' => 'won', 'probability' => 100]);
                        Notification::make()
                            ->title('Felicitări! Oportunitate marcată câștigată')
                            ->success()
                            ->send();
                    }),
                Action::make('markLost')
                    ->label('Pierdută')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Opportunity $record): bool => !in_array($record->status, ['won', 'lost']))
                    ->requiresConfirmation()
                    ->modalHeading('Marchează ca pierdută')
                    ->modalDescription('Ești sigur că vrei să marchezi această oportunitate ca pierdută?')
                    ->modalSubmitActionLabel('Da, marchează pierdută')
                    ->action(function (Opportunity $record): void {
                        $record->update(['status' => 'lost', 'probability' => 0]);
                        Notification::make()
                            ->title('Oportunitate marcată pierdută')
                            ->danger()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('exportSelected')
                        ->label('Export Excel selectate')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (Collection $records) {
                            $ids = $records->pluck('id')->join(',');
                            return redirect()->route('opportunities.export', ['ids' => $ids]);
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('expected_close_date');
    }
}
