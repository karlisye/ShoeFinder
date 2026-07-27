<?php

namespace App\Domain\Catalogue\Pricing;

use App\Models\RetailerListing;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class ListingFreshness
{
    public function cutoff(?CarbonInterface $at = null): CarbonImmutable
    {
        $reference = $at === null
            ? CarbonImmutable::now()
            : CarbonImmutable::instance($at);

        return $reference->subHours($this->staleAfterHours());
    }

    public function isFresh(
        RetailerListing $listing,
        ?CarbonInterface $at = null,
    ): bool {
        return $listing->last_checked_at !== null
            && $listing->last_checked_at->greaterThanOrEqualTo(
                $this->cutoff($at),
            );
    }

    public function isStale(
        RetailerListing $listing,
        ?CarbonInterface $at = null,
    ): bool {
        return ! $this->isFresh($listing, $at);
    }

    public function staleAfterHours(): int
    {
        return max(1, (int) config('catalogue.offer_stale_after_hours', 168));
    }
}
