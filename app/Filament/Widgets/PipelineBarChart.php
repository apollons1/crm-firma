<?php

namespace App\Filament\Widgets;

use App\Models\Opportunity;
use Filament\Widgets\ChartWidget;

class PipelineBarChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'md'      => 1,
    ];

    protected ?string $heading = 'Valoare pipeline pe etape (RON)';

    protected ?string $maxHeight = '300px';

    protected ?string $pollingInterval = '60s';

    private const STAGES = [
        'lead'        => ['label' => 'Lead',     'color' => '#6b7280'],
        'qualified'   => ['label' => 'Calificat', 'color' => '#3b82f6'],
        'proposal'    => ['label' => 'Propunere', 'color' => '#eab308'],
        'negotiation' => ['label' => 'Negociere', 'color' => '#f97316'],
    ];

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        // un singur query — valoare + count per etapă
        $rows = Opportunity::selectRaw('status, COALESCE(SUM(estimated_value), 0) as total, COUNT(*) as cnt')
            ->whereIn('status', array_keys(self::STAGES))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $labels = [];
        $values = [];
        $colors = [];
        $counts = [];

        foreach (self::STAGES as $status => $config) {
            $labels[] = $config['label'];
            $values[] = round((float) ($rows[$status]->total ?? 0));
            $colors[] = $config['color'];
            $counts[] = (int) ($rows[$status]->cnt ?? 0);
        }

        return [
            'labels'   => $labels,
            'datasets' => [
                [
                    'label'           => 'Valoare (RON)',
                    'data'            => $values,
                    'counts'          => $counts,        // transmis pentru tooltip custom
                    'backgroundColor' => $colors,
                    'borderColor'     => $colors,
                    'borderWidth'     => 1,
                    'borderRadius'    => 6,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => [
                    'callbacks' => [
                        'label' => "function(ctx) {
                            const val = ctx.parsed.y;
                            const cnt = ctx.dataset.counts[ctx.dataIndex];
                            const formatted = new Intl.NumberFormat('ro-RO').format(val);
                            return [' Valoare: ' + formatted + ' RON', ' Oportunități: ' + cnt];
                        }",
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks'       => [
                        'callback' => "function(val) {
                            return new Intl.NumberFormat('ro-RO', {maximumFractionDigits: 0}).format(val) + ' RON';
                        }",
                    ],
                    'grid' => ['color' => 'rgba(0,0,0,0.05)'],
                ],
                'x' => [
                    'grid' => ['display' => false],
                ],
            ],
        ];
    }
}
