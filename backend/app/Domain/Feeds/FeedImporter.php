<?php

namespace App\Domain\Feeds;

use App\Domain\Feeds\Data\FeedImportItem;
use App\Domain\Feeds\Data\FeedImportReport;
use App\Domain\Feeds\Data\FeedMatch;
use App\Domain\Feeds\Data\FeedRecord;
use App\Enums\ListingSourceType;
use App\Models\Retailer;
use App\Models\RetailerListing;
use Illuminate\Support\Facades\DB;

class FeedImporter
{
    public function __construct(
        private readonly FeedAdapterRegistry $adapters,
        private readonly FeedRecordValidator $validator,
        private readonly FeedProductMatcher $matcher,
        private readonly FeedListingSynchronizer $synchronizer,
    ) {}

    public function import(
        Retailer $retailer,
        string $feedRetailer,
        string $path,
        bool $apply = false,
    ): FeedImportReport {
        $read = $this->adapters->forRetailer($feedRetailer)->read($path);
        $duplicateIssues = $this->validator->duplicateIssues($read->records);
        $validationIssues = [];

        foreach ($read->records as $record) {
            $validationIssues[$record->sourceRecord] = [
                ...$this->validator->validate($record),
                ...($duplicateIssues[$record->sourceRecord] ?? []),
            ];
        }

        $canApply = $apply
            && $read->issues === []
            && collect($validationIssues)->every(
                fn (array $recordIssues): bool => $recordIssues === [],
            );

        $run = function () use (
            $retailer,
            $feedRetailer,
            $path,
            $canApply,
            $read,
            $validationIssues,
        ): FeedImportReport {
            $items = [];
            $issues = $read->issues;
            $observedExternalIds = [];
            $observedSkus = [];

            foreach ($read->records as $record) {
                $this->collectIdentity($record, $observedExternalIds, $observedSkus);
                $recordIssues = $validationIssues[$record->sourceRecord];

                if ($recordIssues !== []) {
                    array_push($issues, ...$recordIssues);
                    $items[] = new FeedImportItem(
                        $record->sourceRecord,
                        $record->identity(),
                        'invalid',
                        $recordIssues[0]->code,
                        $record->data,
                        $record->raw,
                        array_map(
                            fn ($issue): array => $issue->toArray(),
                            $recordIssues,
                        ),
                    );

                    continue;
                }

                $match = $this->matcher->match($retailer, $record);

                if ($match->action === 'manual_review') {
                    $items[] = new FeedImportItem(
                        $record->sourceRecord,
                        $record->identity(),
                        'manual_review',
                        $match->reason,
                        $record->data,
                        $record->raw,
                        matchedListingId: $match->listing?->getKey(),
                        matchedVariantId: $match->variant?->getKey(),
                    );

                    continue;
                }

                $outcome = $this->outcome($match, $record);

                if ($canApply) {
                    $this->synchronizer->sync(
                        $retailer,
                        $record,
                        $match->listing,
                        $match->variant,
                    );
                }

                $items[] = new FeedImportItem(
                    $record->sourceRecord,
                    $record->identity(),
                    $outcome,
                    $match->reason,
                    $record->data,
                    $record->raw,
                    matchedListingId: $match->listing?->getKey(),
                    matchedVariantId: $match->variant?->getKey(),
                );
            }

            foreach (
                $this->missingListings(
                    $retailer,
                    $observedExternalIds,
                    $observedSkus,
                ) as $listing
            ) {
                $items[] = new FeedImportItem(
                    null,
                    $listing->retailer_external_id
                        ?? $listing->retailer_sku
                        ?? "listing-{$listing->getKey()}",
                    'missing',
                    'not_present_in_snapshot',
                    matchedListingId: $listing->getKey(),
                    matchedVariantId: $listing->shoe_variant_id,
                );
            }

            return new FeedImportReport(
                $feedRetailer,
                $path,
                $canApply,
                $items,
                $issues,
            );
        };

        return $canApply ? DB::transaction($run) : $run();
    }

    private function collectIdentity(
        FeedRecord $record,
        array &$externalIds,
        array &$skus,
    ): void {
        if (filled($record->data['retailer_external_id'] ?? null)) {
            $externalIds[] = $record->data['retailer_external_id'];
        }

        if (filled($record->data['retailer_sku'] ?? null)) {
            $skus[] = $record->data['retailer_sku'];
        }
    }

    private function outcome(FeedMatch $match, FeedRecord $record): string
    {
        if ($match->action === 'create') {
            return 'created';
        }

        if (! $record->data['active']) {
            return 'unavailable';
        }

        return $this->hasBusinessChanges($match->listing, $record)
            ? 'updated'
            : 'unchanged';
    }

    private function hasBusinessChanges(
        RetailerListing $listing,
        FeedRecord $record,
    ): bool {
        $attributes = $this->synchronizer->listingAttributes($record);
        unset($attributes['raw_payload'], $attributes['last_checked_at']);

        foreach ($attributes as $field => $expected) {
            $actual = $listing->{$field};

            if ($field === 'source_type') {
                $actual = $actual?->value ?? $actual;
            }

            if ($field === 'active') {
                if ((bool) $actual !== $expected) {
                    return true;
                }

                continue;
            }

            if ($this->scalar($actual) !== $this->scalar($expected)) {
                return true;
            }
        }

        $existingSizes = $listing->listingSizes()
            ->with('size')
            ->get()
            ->mapWithKeys(fn ($size): array => [
                $size->size->label => [
                    'in_stock' => $size->in_stock,
                    'price' => $this->scalar($size->price),
                ],
            ])
            ->all();
        $incomingSizes = collect($record->data['sizes'])
            ->mapWithKeys(fn (array $size): array => [
                $size['eu_size'] => [
                    'in_stock' => $size['in_stock'],
                    'price' => $this->scalar($size['price']),
                ],
            ])
            ->all();

        ksort($existingSizes);
        ksort($incomingSizes);

        return $existingSizes !== $incomingSizes;
    }

    private function scalar(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    private function missingListings(
        Retailer $retailer,
        array $externalIds,
        array $skus,
    ) {
        return $retailer->listings()
            ->where('source_type', ListingSourceType::Feed->value)
            ->get()
            ->filter(function (RetailerListing $listing) use ($externalIds, $skus): bool {
                $externalObserved = filled($listing->retailer_external_id)
                    && in_array($listing->retailer_external_id, $externalIds, true);
                $skuObserved = filled($listing->retailer_sku)
                    && in_array($listing->retailer_sku, $skus, true);

                return ! $externalObserved && ! $skuObserved;
            })
            ->values();
    }
}
