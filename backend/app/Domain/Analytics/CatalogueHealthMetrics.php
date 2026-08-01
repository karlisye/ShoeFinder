<?php

namespace App\Domain\Analytics;

use App\Domain\Catalogue\Pricing\ListingFreshness;
use App\Domain\Catalogue\Pricing\QualifyingListingSizeQuery;
use App\Models\RetailerListing;
use App\Models\Shoe;
use App\Models\ShoeVariant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final readonly class CatalogueHealthMetrics
{
    public function __construct(
        private QualifyingListingSizeQuery $qualifyingListingSizes,
        private ListingFreshness $freshness,
    ) {}

    /**
     * @return array{
     *     public_shoes: int,
     *     public_variants: int,
     *     qualifying_listings: int,
     *     qualifying_retailers: int,
     *     stale_listings: int,
     *     fresh_listings_without_stock: int,
     *     variants_without_primary_image: int,
     *     shoes_without_qualifying_listing: int,
     *     stale_after_hours: int
     * }
     */
    public function summary(?CarbonInterface $at = null): array
    {
        $publicShoes = $this->publicShoesQuery();
        $publicVariants = $this->publicVariantsQuery();
        $activeListings = $this->activeListingsQuery();
        $qualifyingRows = $this->qualifyingListingSizes->build('EUR', $at);
        $publicShoeCount = (clone $publicShoes)->count();
        $qualifyingShoeCount = $this->distinctCount(
            $qualifyingRows,
            'qualified_shoes.id',
        );

        return [
            'public_shoes' => $publicShoeCount,
            'public_variants' => (clone $publicVariants)->count(),
            'qualifying_listings' => $this->distinctCount(
                $qualifyingRows,
                'qualified_listings.id',
            ),
            'qualifying_retailers' => $this->distinctCount(
                $qualifyingRows,
                'qualified_retailers.id',
            ),
            'stale_listings' => (clone $activeListings)
                ->where(function (EloquentBuilder $query) use ($at): void {
                    $query
                        ->whereNull('last_checked_at')
                        ->orWhere(
                            'last_checked_at',
                            '<',
                            $this->freshness->cutoff($at),
                        );
                })
                ->count(),
            'fresh_listings_without_stock' => (clone $activeListings)
                ->where(
                    'last_checked_at',
                    '>=',
                    $this->freshness->cutoff($at),
                )
                ->whereDoesntHave(
                    'listingSizes',
                    fn (EloquentBuilder $query) => $query
                        ->where('in_stock', true)
                        ->whereHas(
                            'size',
                            fn (EloquentBuilder $query) => $query
                                ->where('active', true),
                        ),
                )
                ->count(),
            'variants_without_primary_image' => (clone $publicVariants)
                ->whereDoesntHave(
                    'images',
                    fn (EloquentBuilder $query) => $query
                        ->where('is_primary', true),
                )
                ->count(),
            'shoes_without_qualifying_listing' => max(
                0,
                $publicShoeCount - $qualifyingShoeCount,
            ),
            'stale_after_hours' => $this->freshness->staleAfterHours(),
        ];
    }

    /**
     * @return EloquentBuilder<Shoe>
     */
    private function publicShoesQuery(): EloquentBuilder
    {
        return Shoe::query()
            ->where('active', true)
            ->whereHas(
                'brand',
                fn (EloquentBuilder $query) => $query
                    ->where('active', true),
            )
            ->whereHas(
                'category',
                fn (EloquentBuilder $query) => $query
                    ->where('active', true),
            );
    }

    /**
     * @return EloquentBuilder<ShoeVariant>
     */
    private function publicVariantsQuery(): EloquentBuilder
    {
        return ShoeVariant::query()
            ->where('active', true)
            ->whereHas(
                'colour',
                fn (EloquentBuilder $query) => $query
                    ->where('active', true),
            )
            ->whereHas(
                'shoe',
                fn (EloquentBuilder $query) => $query
                    ->where('active', true)
                    ->whereHas(
                        'brand',
                        fn (EloquentBuilder $query) => $query
                            ->where('active', true),
                    )
                    ->whereHas(
                        'category',
                        fn (EloquentBuilder $query) => $query
                            ->where('active', true),
                    ),
            );
    }

    /**
     * @return EloquentBuilder<RetailerListing>
     */
    private function activeListingsQuery(): EloquentBuilder
    {
        return RetailerListing::query()
            ->where('active', true)
            ->whereHas(
                'retailer',
                fn (EloquentBuilder $query) => $query
                    ->where('active', true),
            )
            ->whereHas(
                'variant',
                fn (EloquentBuilder $query) => $query
                    ->where('active', true)
                    ->whereHas(
                        'colour',
                        fn (EloquentBuilder $query) => $query
                            ->where('active', true),
                    )
                    ->whereHas(
                        'shoe',
                        fn (EloquentBuilder $query) => $query
                            ->where('active', true)
                            ->whereHas(
                                'brand',
                                fn (EloquentBuilder $query) => $query
                                    ->where('active', true),
                            )
                            ->whereHas(
                                'category',
                                fn (EloquentBuilder $query) => $query
                                    ->where('active', true),
                            ),
                    ),
            );
    }

    private function distinctCount(
        QueryBuilder $query,
        string $column,
    ): int {
        return (clone $query)
            ->distinct()
            ->count($column);
    }
}
