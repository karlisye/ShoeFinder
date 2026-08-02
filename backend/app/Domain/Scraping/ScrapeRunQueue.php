<?php

namespace App\Domain\Scraping;

use App\Enums\ListingSourceType;
use App\Jobs\PreviewScrapeRun;
use App\Models\Retailer;
use App\Models\RetailerListing;
use App\Models\ScrapeRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LogicException;

class ScrapeRunQueue
{
    public function start(?Retailer $retailer = null, ?User $user = null): ScrapeRun
    {
        if (! config('scraper.enabled', true)) {
            throw new LogicException('The product-page scraper is disabled.');
        }

        $run = Cache::lock('scrape-runs:create', 10)->block(5, function () use ($retailer, $user): ScrapeRun {
            return DB::transaction(function () use ($retailer, $user): ScrapeRun {
                if (ScrapeRun::query()->whereIn('status', [
                    ScrapeRun::STATUS_QUEUED,
                    ScrapeRun::STATUS_SCRAPING,
                    ScrapeRun::STATUS_APPLY_QUEUED,
                    ScrapeRun::STATUS_APPLYING,
                ])->exists()) {
                    throw new LogicException('Another scrape run is already in progress.');
                }

                $listings = $this->eligibleListings($retailer)->get();

                if ($listings->isEmpty()) {
                    throw new LogicException('No eligible manual listings were found.');
                }

                $run = ScrapeRun::query()->create([
                    'user_id' => $user?->getKey(),
                    'retailer_id' => $retailer?->getKey(),
                    'status' => ScrapeRun::STATUS_QUEUED,
                    'total_count' => $listings->count(),
                ]);

                foreach ($listings->values() as $index => $listing) {
                    $run->items()->create([
                        'retailer_listing_id' => $listing->getKey(),
                        'position' => $index + 1,
                        'status' => 'pending',
                        'product_url' => $listing->product_url,
                        'listing_label' => $this->listingLabel($listing),
                        'baseline' => $this->baseline($listing),
                    ]);
                }

                return $run;
            });
        });

        PreviewScrapeRun::dispatch($run->getKey())
            ->onQueue($this->queue())
            ->afterCommit();

        return $run->refresh();
    }

    public function eligibleCount(?Retailer $retailer = null): int
    {
        return $this->eligibleListings($retailer)->count();
    }

    /** @return Builder<RetailerListing> */
    private function eligibleListings(?Retailer $retailer): Builder
    {
        $supportedRetailers = array_keys(config('scraper.retailers', []));

        if ($retailer !== null && ! in_array($retailer->slug, $supportedRetailers, true)) {
            throw new LogicException('The selected retailer has no scraper adapter.');
        }

        return RetailerListing::query()
            ->with([
                'retailer',
                'variant.shoe',
                'variant.colour',
                'listingSizes.size',
            ])
            ->where('source_type', ListingSourceType::Manual->value)
            ->whereHas('retailer', fn (Builder $query): Builder => $query
                ->whereIn('slug', $supportedRetailers))
            ->when($retailer !== null, fn (Builder $query): Builder => $query
                ->where('retailer_id', $retailer->getKey()))
            ->orderBy('retailer_id')
            ->orderBy('id');
    }

    private function listingLabel(RetailerListing $listing): string
    {
        return implode(' / ', [
            $listing->retailer->name,
            $listing->variant->shoe->name,
            $listing->variant->colour->name,
        ]);
    }

    /** @return array<string, mixed> */
    private function baseline(RetailerListing $listing): array
    {
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

    private function queue(): string
    {
        return (string) config('scraper.queue', 'scrapes');
    }
}
