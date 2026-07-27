<?php

namespace App\Domain\Catalogue\Pricing;

use App\Models\Shoe;
use App\Models\ShoeVariant;
use App\Models\Size;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use stdClass;

final readonly class LowestPriceFinder
{
    public function __construct(private ListingFreshness $freshness) {}

    public function forShoe(
        Shoe $shoe,
        ?Size $size = null,
        string $currency = 'EUR',
        ?CarbonInterface $at = null,
    ): ?LowestPrice {
        $query = $this->qualifyingQuery($currency, $at)
            ->where('shoes.id', $shoe->getKey());

        if ($size !== null) {
            $query->where('retailer_listing_sizes.size_id', $size->getKey());
        }

        return $this->firstResult($query, $currency);
    }

    public function forVariant(
        ShoeVariant $variant,
        ?Size $size = null,
        string $currency = 'EUR',
        ?CarbonInterface $at = null,
    ): ?LowestPrice {
        $query = $this->qualifyingQuery($currency, $at)
            ->where('shoe_variants.id', $variant->getKey());

        if ($size !== null) {
            $query->where('retailer_listing_sizes.size_id', $size->getKey());
        }

        return $this->firstResult($query, $currency);
    }

    private function qualifyingQuery(
        string $currency,
        ?CarbonInterface $at,
    ): Builder {
        $this->ensureCurrency($currency);

        return DB::table('retailer_listing_sizes')
            ->select([
                'retailer_listing_sizes.id as listing_size_id',
                'retailer_listing_sizes.size_id',
                'retailer_listings.id as retailer_listing_id',
                'retailer_listings.shoe_variant_id',
            ])
            ->selectRaw(
                'COALESCE(retailer_listing_sizes.price, retailer_listings.current_price) as effective_price',
            )
            ->join(
                'retailer_listings',
                'retailer_listings.id',
                '=',
                'retailer_listing_sizes.retailer_listing_id',
            )
            ->join(
                'retailers',
                'retailers.id',
                '=',
                'retailer_listings.retailer_id',
            )
            ->join(
                'shoe_variants',
                'shoe_variants.id',
                '=',
                'retailer_listings.shoe_variant_id',
            )
            ->join('shoes', 'shoes.id', '=', 'shoe_variants.shoe_id')
            ->where('retailer_listing_sizes.in_stock', true)
            ->where('retailer_listings.active', true)
            ->where('retailer_listings.currency', $currency)
            ->where(
                'retailer_listings.last_checked_at',
                '>=',
                $this->freshness->cutoff($at),
            )
            ->where('retailers.active', true)
            ->where('shoe_variants.active', true)
            ->where('shoes.active', true);
    }

    private function firstResult(
        Builder $query,
        string $currency,
    ): ?LowestPrice {
        $result = $query
            ->orderBy('effective_price')
            ->orderBy('retailer_listings.id')
            ->orderBy('retailer_listing_sizes.id')
            ->first();

        return $result === null
            ? null
            : $this->toLowestPrice($result, $currency);
    }

    private function toLowestPrice(
        stdClass $result,
        string $currency,
    ): LowestPrice {
        return new LowestPrice(
            amount: $this->normalizeAmount($result->effective_price),
            currency: $currency,
            retailerListingId: (int) $result->retailer_listing_id,
            listingSizeId: (int) $result->listing_size_id,
            sizeId: (int) $result->size_id,
            shoeVariantId: (int) $result->shoe_variant_id,
        );
    }

    private function ensureCurrency(string $currency): void
    {
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException(
                'Currency must be a three-letter uppercase code.',
            );
        }
    }

    private function normalizeAmount(mixed $amount): string
    {
        $value = (string) $amount;

        if (preg_match('/^(\d+)(?:\.(\d+))?$/', $value, $matches) !== 1) {
            throw new InvalidArgumentException('Price must be a positive decimal value.');
        }

        $fraction = str_pad($matches[2] ?? '', 2, '0');

        return $matches[1].'.'.substr($fraction, 0, 2);
    }
}
