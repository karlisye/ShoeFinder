<?php

namespace App\Filament\Widgets;

use App\Domain\Analytics\OutboundClickMetrics;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OutboundClickStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = -3;

    protected ?string $heading = 'Outbound clicks';

    protected ?string $description = 'Tracked redirects to retailer and affiliate URLs.';

    protected ?string $pollingInterval = '60s';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $summary = app(OutboundClickMetrics::class)->summary();

        return [
            Stat::make('Today', number_format($summary['today']))
                ->description("Yesterday: {$summary['yesterday']}")
                ->icon(Heroicon::OutlinedCursorArrowRays),
            Stat::make('Last 7 days', number_format($summary['last_7_days']))
                ->description("Previous 7 days: {$summary['previous_7_days']}")
                ->icon(Heroicon::OutlinedCalendarDays),
            Stat::make('Last 30 days', number_format($summary['last_30_days']))
                ->description("Previous 30 days: {$summary['previous_30_days']}")
                ->icon(Heroicon::OutlinedCalendarDateRange),
            Stat::make('All time', number_format($summary['all_time']))
                ->description('Recorded redirects')
                ->icon(Heroicon::OutlinedChartBar),
        ];
    }
}
