<?php

namespace App\Filament\Widgets;

use App\Models\Opportunity;
use Filament\Widgets\ChartWidget;

class OpportunitiesDonutChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'md'      => 1,
    ];

    protected ?string $heading = 'Distribuția oportunităților pe status';

    protected ?string $maxHeight = '300px';

    protected ?string $pollingInterval = '60s';

    private const STATUS_CONFIG = [
        'lead'        => ['label' => 'Lead',      'color' => '#6b7280'],
        'qualified'   => ['label' => 'Calificat',  'color' => '#3b82f6'],
        'proposal'    => ['label' => 'Propunere',  'color' => '#eab308'],
        'negotiation' => ['label' => 'Negociere',  'color' => '#f97316'],
        'won'         => ['label' => 'Câștigat',   'color' => '#22c55e'],
        'lost'        => ['label' => 'Pierdut',    'color' => '#ef4444'],
    ];

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $counts = Opportunity::selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $labels     = [];
        $data       = [];
        $colors     = [];

        foreach (self::STATUS_CONFIG as $status => $config) {
            $labels[] = $config['label'];
            $data[]   = (int) ($counts[$status] ?? 0);
            $colors[] = $config['color'];
        }

        return [
            'labels'   => $labels,
            'datasets' => [
                [
                    'data'            => $data,
                    'backgroundColor' => $colors,
                    'borderWidth'     => 2,
                    'hoverOffset'     => 6,
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
                    'position' => 'right',
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => "function(ctx) {
                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? Math.round(ctx.parsed / total * 100) : 0;
                            return ' ' + ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                        }",
                    ],
                ],
            ],
            'cutout' => '65%',
        ];
    }
}
