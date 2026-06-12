<?php

namespace App\Filament\Widgets;

use App\Models\Client;
use App\Models\Opportunity;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class CrmStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    private const ACTIVE_STATUSES = ['lead', 'qualified', 'proposal', 'negotiation'];

    /**
     * Detectăm sales_rep o singură dată per request.
     * admin / super_admin / sales_manager → statistici globale (CRM-ul întreg).
     * sales_rep → statistici personale (doar user_id = auth()->id()).
     */
    private function isSalesRep(): bool
    {
        return auth()->user()?->hasRole('sales_rep') ?? false;
    }

    /**
     * Returnează un query de Opportunity pre-filtrat dacă e sales_rep.
     * Toți ceilalți primesc query-ul complet.
     */
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
        return $this->isSalesRep()
            ? $this->getSalesRepStats()
            : $this->getGlobalStats();
    }

    // ── Statistici globale (admin, sales_manager) ──────────────────────────────

    private function getGlobalStats(): array
    {
        $now         = Carbon::now();
        $oneMonthAgo = $now->copy()->subMonth();

        $activeClients          = Client::where('status', 'active')->count();
        $activeClientsLastMonth = Client::where('status', 'active')
            ->where('created_at', '<', $oneMonthAgo)->count();
        $clientDiff = $activeClients - $activeClientsLastMonth;

        $inProgress          = Opportunity::whereIn('status', self::ACTIVE_STATUSES)->count();
        $inProgressLastMonth = Opportunity::whereIn('status', self::ACTIVE_STATUSES)
            ->where('created_at', '<', $oneMonthAgo)->count();
        $oppDiff = $inProgress - $inProgressLastMonth;

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

        $wonThisMonth      = Opportunity::where('status', 'won')
            ->whereYear('updated_at', $now->year)->whereMonth('updated_at', $now->month)->count();
        $wonValueThisMonth = (float) Opportunity::where('status', 'won')
            ->whereYear('updated_at', $now->year)->whereMonth('updated_at', $now->month)
            ->selectRaw('COALESCE(SUM(estimated_value), 0) as total')->value('total');
        $wonLastMonth = Opportunity::where('status', 'won')
            ->whereYear('updated_at', $oneMonthAgo->year)->whereMonth('updated_at', $oneMonthAgo->month)->count();
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
                ->description($this->pctText($pipelinePct) . ' · ponderat după probabilitate')
                ->descriptionIcon($this->diffIcon($pipelinePct), IconPosition::Before)
                ->descriptionColor($this->diffColor($pipelinePct))
                ->icon('heroicon-o-banknotes')
                ->color('warning'),

            Stat::make('Câștigate Luna Aceasta', $wonThisMonth)
                ->description(number_format($wonValueThisMonth, 0, ',', '.') . ' RON · ' . $this->diffText($wonDiff, 'vs luna trecută'))
                ->descriptionIcon($this->diffIcon($wonDiff), IconPosition::Before)
                ->descriptionColor($this->diffColor($wonDiff))
                ->icon('heroicon-o-trophy')
                ->color('success'),
        ];
    }

    // ── Statistici personale (sales_rep) ───────────────────────────────────────

    private function getSalesRepStats(): array
    {
        $now         = Carbon::now();
        $oneMonthAgo = $now->copy()->subMonth();

        // Oportunităţile mele active
        $myActive          = $this->oppQuery()->whereIn('status', self::ACTIVE_STATUSES)->count();
        $myActiveLastMonth = $this->oppQuery()->whereIn('status', self::ACTIVE_STATUSES)
            ->where('created_at', '<', $oneMonthAgo)->count();
        $activeDiff = $myActive - $myActiveLastMonth;

        // Pipeline-ul meu ponderat
        $myPipeline = (float) $this->oppQuery()->whereIn('status', self::ACTIVE_STATUSES)
            ->selectRaw('COALESCE(SUM(estimated_value * probability / 100), 0) as total')
            ->value('total');
        $myPipelineLastMonth = (float) $this->oppQuery()->whereIn('status', self::ACTIVE_STATUSES)
            ->where('created_at', '<', $oneMonthAgo)
            ->selectRaw('COALESCE(SUM(estimated_value * probability / 100), 0) as total')
            ->value('total');
        $pipelinePct = $myPipelineLastMonth > 0
            ? (int) round((($myPipeline - $myPipelineLastMonth) / $myPipelineLastMonth) * 100)
            : ($myPipeline > 0 ? 100 : 0);

        // Câștigate de mine luna aceasta
        $myWon = $this->oppQuery()->where('status', 'won')
            ->whereYear('updated_at', $now->year)->whereMonth('updated_at', $now->month)->count();
        $myWonValue = (float) $this->oppQuery()->where('status', 'won')
            ->whereYear('updated_at', $now->year)->whereMonth('updated_at', $now->month)
            ->selectRaw('COALESCE(SUM(estimated_value), 0) as total')->value('total');
        $myWonLast  = $this->oppQuery()->where('status', 'won')
            ->whereYear('updated_at', $oneMonthAgo->year)->whereMonth('updated_at', $oneMonthAgo->month)->count();
        $wonDiff = $myWon - $myWonLast;

        // Scadente în 14 zile
        $closingSoon = $this->oppQuery()->whereIn('status', self::ACTIVE_STATUSES)
            ->whereBetween('expected_close_date', [$now->toDateString(), $now->copy()->addDays(14)->toDateString()])
            ->count();

        return [
            Stat::make('Oportunitățile Mele Active', $myActive)
                ->description($this->diffText($activeDiff, 'față de luna trecută'))
                ->descriptionIcon($this->diffIcon($activeDiff), IconPosition::Before)
                ->descriptionColor($this->diffColor($activeDiff))
                ->icon('heroicon-o-arrow-trending-up')
                ->color('info'),

            Stat::make('Pipeline-ul Meu Ponderat', number_format($myPipeline, 0, ',', '.') . ' RON')
                ->description($this->pctText($pipelinePct) . ' · ponderat după probabilitate')
                ->descriptionIcon($this->diffIcon($pipelinePct), IconPosition::Before)
                ->descriptionColor($this->diffColor($pipelinePct))
                ->icon('heroicon-o-banknotes')
                ->color('warning'),

            Stat::make('Câștigate de Mine Luna Aceasta', $myWon)
                ->description(number_format($myWonValue, 0, ',', '.') . ' RON · ' . $this->diffText($wonDiff, 'vs luna trecută'))
                ->descriptionIcon($this->diffIcon($wonDiff), IconPosition::Before)
                ->descriptionColor($this->diffColor($wonDiff))
                ->icon('heroicon-o-trophy')
                ->color('success'),

            Stat::make('Scadente în 14 Zile', $closingSoon)
                ->description($closingSoon > 0 ? 'Necesită atenție urgentă' : 'Nimic scadent imediat')
                ->descriptionIcon(
                    $closingSoon > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle',
                    IconPosition::Before
                )
                ->descriptionColor($closingSoon > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-calendar-days')
                ->color($closingSoon > 0 ? 'warning' : 'gray'),
        ];
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function diffText(int $diff, string $suffix): string
    {
        return ($diff > 0 ? '+' : '') . "{$diff} {$suffix}";
    }

    private function pctText(int $pct): string
    {
        return ($pct > 0 ? '+' : '') . "{$pct}% vs luna trecută";
    }

    private function diffIcon(int $diff): string
    {
        return match (true) {
            $diff > 0 => 'heroicon-m-arrow-trending-up',
            $diff < 0 => 'heroicon-m-arrow-trending-down',
            default   => 'heroicon-m-minus',
        };
    }

    private function diffColor(int $diff): string
    {
        return match (true) {
            $diff > 0 => 'success',
            $diff < 0 => 'danger',
            default   => 'gray',
        };
    }
}
