<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Opportunities\OpportunityResource;
use App\Models\Opportunity;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class BlockedOpportunitiesStats extends StatsOverviewWidget
{
    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    /**
     * sales_rep → numără doar oportunitățile proprii blocate.
     * Toți ceilalți → numără global (toată echipa).
     */
    private function isSalesRep(): bool
    {
        return auth()->user()?->hasRole('sales_rep') ?? false;
    }

    private function oppQuery(): Builder
    {
        $query = Opportunity::query();
        if ($this->isSalesRep()) {
            $query->where('user_id', auth()->id());
        }
        return $query;
    }

    protected function getStats(): array
    {
        $now     = Carbon::now();
        $baseUrl = OpportunityResource::getUrl('index');

        // Suffix la URL: sales_rep primește și filtrul de user_id
        $userFilter = $this->isSalesRep()
            ? '&tableFilters[user_id][value]=' . auth()->id()
            : '';

        // Prefix la titlul stat-ului
        $prefix = $this->isSalesRep() ? 'Ale mele · ' : '';

        $stuckLead = $this->oppQuery()
            ->where('status', 'lead')
            ->where('updated_at', '<', $now->copy()->subDays(14))
            ->count();

        $stuckProposal = $this->oppQuery()
            ->where('status', 'proposal')
            ->where('updated_at', '<', $now->copy()->subDays(21))
            ->count();

        $stuckNegotiation = $this->oppQuery()
            ->where('status', 'negotiation')
            ->where('updated_at', '<', $now->copy()->subDays(30))
            ->count();

        return [
            Stat::make("{$prefix}Blocate în Lead 14+ zile", $stuckLead)
                ->description('Trebuie calificate sau închise')
                ->descriptionIcon(
                    $stuckLead > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle',
                    IconPosition::Before,
                )
                ->descriptionColor($stuckLead > 0 ? 'warning' : 'success')
                ->color($stuckLead > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-clock')
                ->url($baseUrl . '?tableFilters[status][value]=lead' . $userFilter),

            Stat::make("{$prefix}Blocate în Propunere 21+ zile", $stuckProposal)
                ->description('Follow-up urgent recomandat')
                ->descriptionIcon(
                    $stuckProposal > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle',
                    IconPosition::Before,
                )
                ->descriptionColor($stuckProposal > 0 ? 'danger' : 'success')
                ->color($stuckProposal > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-clock')
                ->url($baseUrl . '?tableFilters[status][value]=proposal' . $userFilter),

            Stat::make("{$prefix}Blocate în Negociere 30+ zile", $stuckNegotiation)
                ->description('Decizie necesară: închidem?')
                ->descriptionIcon(
                    $stuckNegotiation > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle',
                    IconPosition::Before,
                )
                ->descriptionColor($stuckNegotiation > 0 ? 'danger' : 'success')
                ->color($stuckNegotiation > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-clock')
                ->url($baseUrl . '?tableFilters[status][value]=negotiation' . $userFilter),
        ];
    }
}
