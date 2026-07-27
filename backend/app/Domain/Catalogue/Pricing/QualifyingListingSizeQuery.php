<?php

namespace App\Domain\Catalogue\Pricing;

use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class QualifyingListingSizeQuery
{
    public const EFFECTIVE_PRICE_SQL = 'CAST(COALESCE(qualified_listing_sizes.price, qualified_listings.current_price) AS DECIMAL(12, 2))';

    public function __construct(private ListingFreshness $freshness) {}

    public function build(
        string $currency = 'EUR',
        ?CarbonInterface $at = null,
    ): Builder {
        $this->ensureCurrency($currency);

        return DB::table(
            'retailer_listing_sizes as qualified_listing_sizes',
        )
            ->join(
                'sizes as qualified_sizes',
                'qualified_sizes.id',
                '=',
                'qualified_listing_sizes.size_id',
            )
            ->join(
                'retailer_listings as qualified_listings',
                'qualified_listings.id',
                '=',
                'qualified_listing_sizes.retailer_listing_id',
            )
            ->join(
                'retailers as qualified_retailers',
                'qualified_retailers.id',
                '=',
                'qualified_listings.retailer_id',
            )
            ->join(
                'shoe_variants as qualified_variants',
                'qualified_variants.id',
                '=',
                'qualified_listings.shoe_variant_id',
            )
            ->join(
                'colours as qualified_colours',
                'qualified_colours.id',
                '=',
                'qualified_variants.colour_id',
            )
            ->join(
                'shoes as qualified_shoes',
                'qualified_shoes.id',
                '=',
                'qualified_variants.shoe_id',
            )
            ->join(
                'brands as qualified_brands',
                'qualified_brands.id',
                '=',
                'qualified_shoes.brand_id',
            )
            ->join(
                'categories as qualified_categories',
                'qualified_categories.id',
                '=',
                'qualified_shoes.category_id',
            )
            ->where('qualified_listing_sizes.in_stock', true)
            ->where('qualified_sizes.active', true)
            ->where('qualified_listings.active', true)
            ->where('qualified_listings.currency', $currency)
            ->where(
                'qualified_listings.last_checked_at',
                '>=',
                $this->freshness->cutoff($at),
            )
            ->where('qualified_retailers.active', true)
            ->where('qualified_variants.active', true)
            ->where('qualified_colours.active', true)
            ->where('qualified_shoes.active', true)
            ->where('qualified_brands.active', true)
            ->where('qualified_categories.active', true);
    }

    private function ensureCurrency(string $currency): void
    {
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException(
                'Currency must be a three-letter uppercase code.',
            );
        }
    }
}
