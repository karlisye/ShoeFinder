<?php

namespace App\Domain\Feeds;

use App\Domain\Feeds\Data\FeedMatch;
use App\Domain\Feeds\Data\FeedRecord;
use App\Models\Retailer;
use App\Models\RetailerListing;
use App\Models\ShoeVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FeedProductMatcher
{
    public function match(Retailer $retailer, FeedRecord $record): FeedMatch
    {
        $data = $record->data;
        $externalMatch = $this->listingByIdentity(
            $retailer,
            'retailer_external_id',
            $data['retailer_external_id'] ?? null,
        );
        $skuMatch = $this->listingByIdentity(
            $retailer,
            'retailer_sku',
            $data['retailer_sku'] ?? null,
        );

        if ($externalMatch !== null && $skuMatch !== null && ! $externalMatch->is($skuMatch)) {
            return new FeedMatch('manual_review', 'retailer_identity_conflict');
        }

        $listing = $externalMatch ?? $skuMatch;

        if ($listing !== null) {
            if ($this->listingIdentityConflicts($listing, $data)) {
                return new FeedMatch(
                    'manual_review',
                    'strong_identity_conflict',
                    $listing,
                    $listing->variant,
                );
            }

            return new FeedMatch('update', 'retailer_identity', $listing, $listing->variant);
        }

        $variants = $this->strongVariantCandidates($data);

        if ($variants->count() !== 1) {
            return new FeedMatch(
                'manual_review',
                $variants->isEmpty() ? 'no_strong_match' : 'ambiguous_strong_match',
            );
        }

        $variant = $variants->first();
        $existingForVariant = $variant->retailerListings()
            ->where('retailer_id', $retailer->getKey())
            ->first();

        if ($existingForVariant !== null) {
            return new FeedMatch(
                'manual_review',
                'variant_listing_identity_conflict',
                $existingForVariant,
                $variant,
            );
        }

        return new FeedMatch('create', 'strong_variant_identity', variant: $variant);
    }

    private function listingByIdentity(
        Retailer $retailer,
        string $field,
        mixed $value,
    ): ?RetailerListing {
        if (blank($value)) {
            return null;
        }

        return RetailerListing::query()
            ->with('variant')
            ->where('retailer_id', $retailer->getKey())
            ->where($field, $value)
            ->first();
    }

    private function listingIdentityConflicts(RetailerListing $listing, array $data): bool
    {
        foreach (['gtin', 'manufacturer_style_code'] as $field) {
            if (
                filled($listing->{$field})
                && filled($data[$field] ?? null)
                && $listing->{$field} !== $data[$field]
            ) {
                return true;
            }
        }

        if (
            filled($listing->variant->manufacturer_variant_code)
            && filled($data['manufacturer_variant_code'] ?? null)
            && $listing->variant->manufacturer_variant_code
                !== $data['manufacturer_variant_code']
        ) {
            return true;
        }

        return false;
    }

    /**
     * @return Collection<int, ShoeVariant>
     */
    private function strongVariantCandidates(array $data): Collection
    {
        $candidateSets = [];

        if (filled($data['gtin'] ?? null)) {
            $candidateSets[] = RetailerListing::query()
                ->where('gtin', $data['gtin'])
                ->pluck('shoe_variant_id')
                ->unique()
                ->values();
        }

        if (filled($data['manufacturer_variant_code'] ?? null)) {
            $candidateSets[] = ShoeVariant::query()
                ->where('manufacturer_variant_code', $data['manufacturer_variant_code'])
                ->pluck('id')
                ->unique()
                ->values();
        }

        $styleColourCandidates = $this->styleAndColourCandidates($data);
        if ($styleColourCandidates->isNotEmpty()) {
            $candidateSets[] = $styleColourCandidates;
        }

        $candidateSets = array_values(array_filter(
            $candidateSets,
            fn (Collection $set): bool => $set->isNotEmpty(),
        ));

        if ($candidateSets === []) {
            return collect();
        }

        $candidateIds = $candidateSets[0];
        foreach (array_slice($candidateSets, 1) as $set) {
            $candidateIds = $candidateIds->intersect($set)->values();
        }

        if ($candidateIds->isEmpty()) {
            $candidateIds = collect($candidateSets)
                ->flatten()
                ->unique()
                ->values();
        }

        return ShoeVariant::query()
            ->whereKey($candidateIds)
            ->get();
    }

    /**
     * @return Collection<int, int>
     */
    private function styleAndColourCandidates(array $data): Collection
    {
        if (
            blank($data['manufacturer_style_code'] ?? null)
            || blank($data['colour'] ?? null)
            || blank($data['brand'] ?? null)
        ) {
            return collect();
        }

        $colour = $this->normalizeText($data['colour']);

        return ShoeVariant::query()
            ->whereHas('shoe', function (Builder $query) use ($data): void {
                $query
                    ->where('manufacturer_style_code', $data['manufacturer_style_code'])
                    ->whereHas('brand', fn (Builder $brandQuery) => $brandQuery
                        ->where('name', $data['brand']));
            })
            ->whereHas('colour', function (Builder $query) use ($colour): void {
                $query->whereRaw(
                    "LOWER(REPLACE(name, ' ', '')) = ?",
                    [$colour],
                );
            })
            ->pluck('id')
            ->unique()
            ->values();
    }

    private function normalizeText(string $value): string
    {
        return mb_strtolower(str_replace(' ', '', $value));
    }
}
