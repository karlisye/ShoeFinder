<?php

namespace App\Filament\Widgets;

use App\Domain\Analytics\CatalogueHealthMetrics;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CatalogueIssueStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = -4;

    protected ?string $heading = 'Catalogue issues';

    protected ?string $description = 'Active records that need review for complete price comparison.';

    protected ?string $pollingInterval = '60s';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $summary = app(CatalogueHealthMetrics::class)->summary();

        return [
            $this->issueStat(
                'Stale offers',
                $summary['stale_listings'],
                "Older than {$summary['stale_after_hours']} hours or never checked",
                Heroicon::OutlinedClock,
            ),
            $this->issueStat(
                'Offers without stock',
                $summary['fresh_listings_without_stock'],
                'Fresh active offers without an in-stock size',
                Heroicon::OutlinedArchiveBoxXMark,
            ),
            $this->issueStat(
                'Variants missing a main image',
                $summary['variants_without_primary_image'],
                'Active public colour variants',
                Heroicon::OutlinedPhoto,
            ),
            $this->issueStat(
                'Shoes without an available offer',
                $summary['shoes_without_qualifying_listing'],
                'No fresh in-stock EUR offer',
                Heroicon::OutlinedExclamationTriangle,
            ),
        ];
    }

    private function issueStat(
        string $label,
        int $count,
        string $description,
        Heroicon $icon,
    ): Stat {
        return Stat::make($label, number_format($count))
            ->description($description)
            ->icon($icon)
            ->color($count === 0 ? 'success' : 'warning');
    }
}
