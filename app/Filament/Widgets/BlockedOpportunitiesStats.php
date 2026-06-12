<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Opportunities\OpportunityResource;
use App\Models\Opportunity;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class BlockedOpportunitiesStats extends StatsOverviewWidget
{
    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $now = Carbon::now();

        $stuckLead = Opportunity::where('status', 'lead')
            ->where('updated_at', '<', $now->copy()->subDays(14))
            ->count();

        $stuckProposal = Opportunity::where('status', 'proposal')
            ->where('updated_at', '<', $now->copy()->subDays(21))
            ->count();

        $stuckNegotiation = Opportunity::where('status', 'negotiation')
            ->where('updated_at', '<', $now->copy()->subDays(30))
            ->count();

        $baseUrl = OpportunityResource::getUrl('index');

        return [
            Stat::make('Blocate în Lead 14+ zile', $stuckLead)
                ->description('Trebuie calificate sau închise')
                ->descriptionIcon(
                    $stuckLead > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle',
                    IconPosition::Before,
                )
                ->descriptionColor($stuckLead > 0 ? 'warning' : 'success')
                ->color($stuckLead > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-clock')
                ->url($baseUrl . '?filters[status][value]=lead'),

            Stat::make('Blocate în Propunere 21+ zile', $stuckProposal)
                ->description('Follow-up urgent recomandat')
                ->descriptionIcon(
                    $stuckProposal > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle',
                    IconPosition::Before,
                )
                ->descriptionColor($stuckProposal > 0 ? 'danger' : 'success')
                ->color($stuckProposal > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-clock')
                ->url($baseUrl . '?filters[status][value]=proposal'),

            Stat::make('Blocate în Negociere 30+ zile', $stuckNegotiation)
                ->description('Decizie necesară: închidem?')
                ->descriptionIcon(
                    $stuckNegotiation > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle',
                    IconPosition::Before,
                )
                ->descriptionColor($stuckNegotiation > 0 ? 'danger' : 'success')
                ->color($stuckNegotiation > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-clock')
                ->url($baseUrl . '?filters[status][value]=negotiation'),
        ];
    }
}
