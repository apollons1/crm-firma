<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Opportunities\OpportunityResource;
use App\Models\Opportunity;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopOpportunitiesTable extends TableWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    /**
     * sales_rep → afișează doar propriile oportunități.
     * Toți ceilalți → top 5 global.
     */
    private function isSalesRep(): bool
    {
        return auth()->user()?->hasRole('sales_rep') ?? false;
    }

    public function table(Table $table): Table
    {
        $heading = $this->isSalesRep()
            ? 'Top 5 oportunitățile mele'
            : 'Top 5 oportunități în derulare';

        $query = Opportunity::query()
            ->with('client')
            ->whereIn('status', ['lead', 'qualified', 'proposal', 'negotiation'])
            ->orderByDesc('estimated_value')
            ->limit(5);

        // sales_rep vede doar oportunitățile sale
        if ($this->isSalesRep()) {
            $query->where('user_id', auth()->id());
        }

        return $table
            ->heading($heading)
            ->query($query)
            ->columns([
                TextColumn::make('title')
                    ->label('Titlu')
                    ->searchable(false)
                    ->sortable(false),
                TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable(false)
                    ->sortable(false),
                TextColumn::make('estimated_value')
                    ->label('Valoare')
                    ->formatStateUsing(fn ($state, Opportunity $record): string =>
                        number_format((float) $state, 0, ',', '.') . ' ' . $record->currency
                    )
                    ->sortable(false),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'qualified'   => 'info',
                        'proposal'    => 'warning',
                        'negotiation' => 'primary',
                        default       => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'lead'        => 'Lead',
                        'qualified'   => 'Calificat',
                        'proposal'    => 'Propunere',
                        'negotiation' => 'Negociere',
                        default       => $state,
                    })
                    ->sortable(false),
                TextColumn::make('probability')
                    ->label('Prob.')
                    ->suffix('%')
                    ->sortable(false),
                TextColumn::make('expected_close_date')
                    ->label('Dată închidere')
                    ->date('d.m.Y')
                    ->sortable(false),
            ])
            ->recordUrl(fn (Opportunity $record): string =>
                OpportunityResource::getUrl('edit', ['record' => $record])
            )
            ->paginated(false);
    }
}
