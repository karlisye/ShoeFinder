<?php

namespace App\Filament\Widgets;

use App\Domain\Analytics\CatalogueHealthMetrics;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CatalogueCoverageStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = -6;

    protected ?string $heading = 'Catalogue coverage';

    protected ?string $description = 'Active catalogue records and offers that qualify for public pricing.';

    protected ?string $pollingInterval = '60s';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $summary = app(CatalogueHealthMetrics::class)->summary();

        return [
            Stat::make('Public shoes', number_format($summary['public_shoes']))
                ->description('Active brand, category, and shoe')
                ->icon(Heroicon::OutlinedShoppingBag),
            Stat::make('Active colour variants', number_format($summary['public_variants']))
                ->description('Active public colourways')
                ->icon(Heroicon::OutlinedSwatch),
            Stat::make('Fresh in-stock offers', number_format($summary['qualifying_listings']))
                ->description('Qualifying EUR retailer offers')
                ->icon(Heroicon::OutlinedTag),
            Stat::make('Retailers with live offers', number_format($summary['qualifying_retailers']))
                ->description('Represented by a qualifying offer')
                ->icon(Heroicon::OutlinedBuildingStorefront),
        ];
    }
}
