<?php

namespace App\Filament\Widgets;

use App\Domain\Analytics\OutboundClickMetrics;
use Filament\Widgets\ChartWidget;

class TopRetailersByClicksChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = -1;

    protected ?string $heading = 'Top retailers';

    protected ?string $description = 'Tracked redirects over the last 30 days.';

    protected ?string $emptyStateHeading = 'No outbound clicks yet';

    protected ?string $maxHeight = '300px';

    protected ?string $pollingInterval = '60s';

    protected ?array $options = [
        'indexAxis' => 'y',
        'plugins' => [
            'legend' => [
                'display' => false,
            ],
        ],
        'scales' => [
            'x' => [
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
        $retailers = app(OutboundClickMetrics::class)->topRetailers();

        if ($retailers === []) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Clicks',
                    'data' => array_column($retailers, 'clicks'),
                ],
            ],
            'labels' => array_column($retailers, 'retailer'),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
