<?php

namespace App\Domain\Scraping;

use App\Models\RetailerListing;

class ScrapeListingSnapshot
{
    public function label(RetailerListing $listing): string
    {
        $listing->loadMissing(['retailer', 'variant.shoe', 'variant.colour']);

        return implode(' / ', [
            $listing->retailer->name,
            $listing->variant->shoe->name,
            $listing->variant->colour->name,
        ]);
    }

    /** @return array<string, mixed> */
    public function baseline(RetailerListing $listing): array
    {
        $listing->loadMissing('listingSizes.size');

        return [
            'current_price' => $listing->current_price,
            'original_price' => $listing->original_price,
            'currency' => $listing->currency,
            'active' => $listing->active,
            'last_checked_at' => $listing->last_checked_at?->toIso8601String(),
            'sizes' => $listing->listingSizes
                ->sortBy(fn ($listingSize): int => $listingSize->size->sort_order)
                ->values()
                ->map(fn ($listingSize): array => [
                    'eu_size' => $listingSize->size->label,
                    'in_stock' => $listingSize->in_stock,
                    'price' => $listingSize->price,
                ])
                ->all(),
        ];
    }
}
