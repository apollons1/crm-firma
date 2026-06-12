<?php

namespace App\Filament\Widgets;

use App\Models\Opportunity;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class OpportunitiesLineChart extends ChartWidget
{
    protected static ?int $sort = 0;

    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Evoluția oportunităților (ultimele 12 luni)';

    protected ?string $maxHeight = '300px';

    protected ?string $pollingInterval = '60s';

    private const MONTH_LABELS = [
        1 => 'Ian', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mai', 6 => 'Iun', 7 => 'Iul', 8 => 'Aug',
        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
    ];

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $startDate = Carbon::now()->startOfMonth()->subMonths(11);

        // 3 interogări grupate — nu 36
        $created = Opportunity::selectRaw('YEAR(created_at) as y, MONTH(created_at) as m, COUNT(*) as cnt')
            ->where('created_at', '>=', $startDate)
            ->groupBy('y', 'm')
            ->get()
            ->keyBy(fn ($r) => "{$r->y}-{$r->m}");

        $won = Opportunity::where('status', 'won')
            ->selectRaw('YEAR(updated_at) as y, MONTH(updated_at) as m, COUNT(*) as cnt')
            ->where('updated_at', '>=', $startDate)
            ->groupBy('y', 'm')
            ->get()
            ->keyBy(fn ($r) => "{$r->y}-{$r->m}");

        $lost = Opportunity::where('status', 'lost')
            ->selectRaw('YEAR(updated_at) as y, MONTH(updated_at) as m, COUNT(*) as cnt')
            ->where('updated_at', '>=', $startDate)
            ->groupBy('y', 'm')
            ->get()
            ->keyBy(fn ($r) => "{$r->y}-{$r->m}");

        $labels = [];
        $createdData = [];
        $wonData = [];
        $lostData = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->startOfMonth()->subMonths($i);
            $key   = "{$month->year}-{$month->month}";

            $labels[]      = self::MONTH_LABELS[$month->month] . ' ' . $month->year;
            $createdData[] = (int) ($created[$key]->cnt ?? 0);
            $wonData[]     = (int) ($won[$key]->cnt ?? 0);
            $lostData[]    = (int) ($lost[$key]->cnt ?? 0);
        }

        return [
            'labels'   => $labels,
            'datasets' => [
                [
                    'label'           => 'Create',
                    'data'            => $createdData,
                    'borderColor'     => '#3b82f6',
                    'backgroundColor' => 'rgba(59,130,246,0.08)',
                    'fill'            => true,
                    'tension'         => 0.4,
                    'pointRadius'     => 4,
                ],
                [
                    'label'           => 'Câștigate',
                    'data'            => $wonData,
                    'borderColor'     => '#22c55e',
                    'backgroundColor' => 'rgba(34,197,94,0.08)',
                    'fill'            => true,
                    'tension'         => 0.4,
                    'pointRadius'     => 4,
                ],
                [
                    'label'           => 'Pierdute',
                    'data'            => $lostData,
                    'borderColor'     => '#ef4444',
                    'backgroundColor' => 'rgba(239,68,68,0.08)',
                    'fill'            => true,
                    'tension'         => 0.4,
                    'pointRadius'     => 4,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display'  => true,
                    'position' => 'top',
                ],
                'tooltip' => [
                    'mode'      => 'index',
                    'intersect' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks'       => ['stepSize' => 1, 'precision' => 0],
                ],
            ],
            'interaction' => [
                'mode'      => 'nearest',
                'axis'      => 'x',
                'intersect' => false,
            ],
        ];
    }
}
