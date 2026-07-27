<?php

namespace App\Observers;

use App\Models\RetailerListing;
use Illuminate\Support\Carbon;

class RetailerListingObserver
{
    public function created(RetailerListing $listing): void
    {
        $this->recordPrice($listing);
    }

    public function updated(RetailerListing $listing): void
    {
        if (! $listing->wasChanged(['current_price', 'original_price'])) {
            return;
        }

        $this->recordPrice($listing);
    }

    private function recordPrice(RetailerListing $listing): void
    {
        $listing->priceChanges()->create([
            'price' => $listing->current_price,
            'original_price' => $listing->original_price,
            'observed_at' => Carbon::now(),
        ]);
    }
}
