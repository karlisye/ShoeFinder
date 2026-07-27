<?php

namespace App\Domain\Catalogue\Pricing;

final readonly class LowestPrice
{
    public function __construct(
        public string $amount,
        public string $currency,
        public int $retailerListingId,
        public int $listingSizeId,
        public int $sizeId,
        public int $shoeVariantId,
    ) {}
}
