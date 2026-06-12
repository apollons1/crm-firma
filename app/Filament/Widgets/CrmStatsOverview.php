<?php

namespace App\Filament\Widgets;

use App\Models\Client;
use App\Models\Opportunity;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class CrmStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    private const ACTIVE_STATUSES = ['lead', 'qualified', 'proposal', 'negotiation'];

    protected function getStats(): array
    {
        $now          = Carbon::now();
        $oneMonthAgo  = $now->copy()->subMonth();

        // ── 1. Clienți Activi ──────────────────────────────────────────────
        $activeClients         = Client::where('status', 'active')->count();
        $activeClientsLastMonth = Client::where('status', 'active')
            ->where('created_at', '<', $oneMonthAgo)
            ->count();
        $clientDiff = $activeClients - $activeClientsLastMonth;

        // ── 2. Oportunități în Derulare ────────────────────────────────────
        $inProgress         = Opportunity::whereIn('status', self::ACTIVE_STATUSES)->count();
        $inProgressLastMonth = Opportunity::whereIn('status', self::ACTIVE_STATUSES)
            ->where('created_at', '<', $oneMonthAgo)
            ->count();
        $oppDiff = $inProgress - $inProgressLastMonth;

        // ── 3. Pipeline Total Ponderat ─────────────────────────────────────
        $pipeline = (float) Opportunity::whereIn('status', self::ACTIVE_STATUSES)
            ->selectRaw('COALESCE(SUM(estimated_value * probability / 100), 0) as total')
            ->value('total');

        $pipelineLastMonth = (float) Opportunity::whereIn('status', self::ACTIVE_STATUSES)
            ->where('created_at', '<', $oneMonthAgo)
            ->selectRaw('COALESCE(SUM(estimated_value * probability / 100), 0) as total')
            ->value('total');

        $pipelinePct = $pipelineLastMonth > 0
            ? (int) round((($pipeline - $pipelineLastMonth) / $pipelineLastMonth) * 100)
            : ($pipeline > 0 ? 100 : 0);

        // ── 4. Câștigate Luna Asta ─────────────────────────────────────────
        $wonThisMonth = Opportunity::where('status', 'won')
            ->whereYear('updated_at', $now->year)
            ->whereMonth('updated_at', $now->month)
            ->count();

        $wonValueThisMonth = (float) Opportunity::where('status', 'won')
            ->whereYear('updated_at', $now->year)
            ->whereMonth('updated_at', $now->month)
            ->selectRaw('COALESCE(SUM(estimated_value), 0) as total')
            ->value('total');

        $wonLastMonth = Opportunity::where('status', 'won')
            ->whereYear('updated_at', $oneMonthAgo->year)
            ->whereMonth('updated_at', $oneMonthAgo->month)
            ->count();

        $wonDiff = $wonThisMonth - $wonLastMonth;

        return [
            Stat::make('Clienți Activi', $activeClients)
                ->description($this->diffText($clientDiff, 'față de luna trecută'))
                ->descriptionIcon($this->diffIcon($clientDiff), IconPosition::Before)
                ->descriptionColor($this->diffColor($clientDiff))
                ->icon('heroicon-o-building-office')
                ->color('success'),

            Stat::make('Oportunități în Derulare', $inProgress)
                ->description($this->diffText($oppDiff, 'față de luna trecută'))
                ->descriptionIcon($this->diffIcon($oppDiff), IconPosition::Before)
                ->descriptionColor($this->diffColor($oppDiff))
                ->icon('heroicon-o-arrow-trending-up')
                ->color('info'),

            Stat::make('Pipeline Total Ponderat', number_format($pipeline, 0, ',', '.') . ' RON')
                ->description($this->pctText($pipelinePct) . ' · Pipeline ponderat după probabilitate')
                ->descriptionIcon($this->diffIcon($pipelinePct), IconPosition::Before)
                ->descriptionColor($this->diffColor($pipelinePct))
                ->icon('heroicon-o-banknotes')
                ->color('warning'),

            Stat::make('Câștigate Luna Aceasta', $wonThisMonth)
                ->description(
                    number_format($wonValueThisMonth, 0, ',', '.') . ' RON · '
                    . $this->diffText($wonDiff, 'vs luna trecută')
                )
                ->descriptionIcon($this->diffIcon($wonDiff), IconPosition::Before)
                ->descriptionColor($this->diffColor($wonDiff))
                ->icon('heroicon-o-trophy')
                ->color('success'),
        ];
    }

    private function diffText(int $diff, string $suffix): string
    {
        $prefix = $diff > 0 ? '+' : '';
        return "{$prefix}{$diff} {$suffix}";
    }

    private function pctText(int $pct): string
    {
        $prefix = $pct > 0 ? '+' : '';
        return "{$prefix}{$pct}% vs luna trecută";
    }

    private function diffIcon(int $diff): string
    {
        if ($diff > 0) return 'heroicon-m-arrow-trending-up';
        if ($diff < 0) return 'heroicon-m-arrow-trending-down';
        return 'heroicon-m-minus';
    }

    private function diffColor(int $diff): string
    {
        if ($diff > 0) return 'success';
        if ($diff < 0) return 'danger';
        return 'gray';
    }
}
