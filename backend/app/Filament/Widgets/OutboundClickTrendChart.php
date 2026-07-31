<?php

namespace App\Filament\Widgets;

use App\Domain\Analytics\OutboundClickMetrics;
use Filament\Widgets\ChartWidget;

class OutboundClickTrendChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = -2;

    protected ?string $heading = 'Clicks over the last 30 days';

    protected ?string $description = 'Daily tracked redirects to retailer offers.';

    protected ?string $maxHeight = '300px';

    protected ?string $pollingInterval = '60s';

    protected ?array $options = [
        'plugins' => [
            'legend' => [
                'display' => false,
            ],
        ],
        'scales' => [
            'y' => [
                'beginAtZero' => true,
                'ticks' => [
                    'precision' => 0,
                ],
            ],
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $trend = app(OutboundClickMetrics::class)->dailyTrend();

        return [
            'datasets' => [
                [
                    'label' => 'Clicks',
                    'data' => $trend['values'],
                    'fill' => true,
                    'tension' => 0.25,
                ],
            ],
            'labels' => $trend['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
