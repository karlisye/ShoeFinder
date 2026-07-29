<?php

namespace App\Domain\Feeds\Data;

use App\Models\RetailerListing;
use App\Models\ShoeVariant;

class FeedMatch
{
    public function __construct(
        public readonly string $action,
        public readonly string $reason,
        public readonly ?RetailerListing $listing = null,
        public readonly ?ShoeVariant $variant = null,
    ) {}
}
