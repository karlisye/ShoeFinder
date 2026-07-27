<?php

namespace App\Domain\Catalogue\Pricing;

use App\Models\Shoe;
use App\Models\ShoeVariant;
use App\Models\Size;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use stdClass;

final readonly class LowestPriceFinder
{
    public function __construct(
        private QualifyingListingSizeQuery $qualifyingListingSizes,
    ) {}

    public function forShoe(
        Shoe $shoe,
        ?Size $size = null,
        string $currency = 'EUR',
        ?CarbonInterface $at = null,
    ): ?LowestPrice {
        $query = $this->qualifyingQuery($currency, $at)
            ->where('qualified_shoes.id', $shoe->getKey());

        if ($size !== null) {
            $query->where('qualified_listing_sizes.size_id', $size->getKey());
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
            ->where('qualified_variants.id', $variant->getKey());

        if ($size !== null) {
            $query->where('qualified_listing_sizes.size_id', $size->getKey());
        }

        return $this->firstResult($query, $currency);
    }

    private function qualifyingQuery(
        string $currency,
        ?CarbonInterface $at,
    ): Builder {
        return $this->qualifyingListingSizes
            ->build($currency, $at)
            ->select([
                'qualified_listing_sizes.id as listing_size_id',
                'qualified_listing_sizes.size_id',
                'qualified_listings.id as retailer_listing_id',
                'qualified_listings.shoe_variant_id',
            ])
            ->selectRaw(
                QualifyingListingSizeQuery::EFFECTIVE_PRICE_SQL
                    .' as effective_price',
            );
    }

    private function firstResult(
        Builder $query,
        string $currency,
    ): ?LowestPrice {
        $result = $query
            ->orderBy('effective_price')
            ->orderBy('qualified_listings.id')
            ->orderBy('qualified_listing_sizes.id')
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
