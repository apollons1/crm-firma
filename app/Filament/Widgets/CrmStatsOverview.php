<?php

namespace App\Filament\Widgets;

use App\Models\Client;
use App\Models\Opportunity;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class CrmStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    protected ?string $pollingInterval = '30s';

    private const ACTIVE_STATUSES = ['lead', 'qualified', 'proposal', 'negotiation'];

    protected function getStats(): array
    {
        $activeClients = Client::where('status', 'active')->count();

        $inProgress = Opportunity::whereIn('status', self::ACTIVE_STATUSES)->count();

        $pipeline = (float) Opportunity::whereIn('status', self::ACTIVE_STATUSES)
            ->selectRaw('COALESCE(SUM(estimated_value * probability / 100), 0) as total')
            ->value('total');

        $wonThisMonth = Opportunity::where('status', 'won')
            ->whereYear('updated_at', Carbon::now()->year)
            ->whereMonth('updated_at', Carbon::now()->month)
            ->count();

        return [
            Stat::make('Clienți Activi', $activeClients)
                ->description('clienți cu status activ')
                ->icon('heroicon-o-building-office')
                ->color('success'),

            Stat::make('Oportunități în Derulare', $inProgress)
                ->description('lead · calificat · propunere · negociere')
                ->icon('heroicon-o-arrow-trending-up')
                ->color('info'),

            Stat::make('Pipeline Total', number_format($pipeline, 0, ',', '.') . ' RON')
                ->description('valoare × probabilitate / 100')
                ->icon('heroicon-o-banknotes')
                ->color('warning'),

            Stat::make('Câștigate Luna Aceasta', $wonThisMonth)
                ->description(Carbon::now()->translatedFormat('F Y'))
                ->icon('heroicon-o-trophy')
                ->color('success'),
        ];
    }
}
