<?php

namespace App\Domain\Feeds;

use App\Domain\Feeds\Data\FeedImportItem;
use App\Domain\Feeds\Data\FeedImportReport;
use App\Domain\Feeds\Data\FeedMatch;
use App\Domain\Feeds\Data\FeedRecord;
use App\Enums\ListingSourceType;
use App\Models\Retailer;
use App\Models\RetailerListing;
use App\Models\Size;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class FeedImporter
{
    public function __construct(
        private readonly FeedAdapterRegistry $adapters,
        private readonly FeedRecordValidator $validator,
        private readonly FeedProductMatcher $matcher,
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
                    );

                    continue;
                }

                $outcome = $this->outcome($match, $record);

                if ($canApply) {
                    $this->persist($retailer, $match, $record);
                }

                $items[] = new FeedImportItem(
                    $record->sourceRecord,
                    $record->identity(),
                    $outcome,
                    $match->reason,
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

    private function persist(
        Retailer $retailer,
        FeedMatch $match,
        FeedRecord $record,
    ): RetailerListing {
        $listing = $match->listing ?? new RetailerListing([
            'shoe_variant_id' => $match->variant->getKey(),
            'retailer_id' => $retailer->getKey(),
        ]);

        $listing->fill($this->listingAttributes($record));
        $listing->save();
        $this->syncSizes($listing, $record);

        return $listing;
    }

    private function listingAttributes(FeedRecord $record): array
    {
        $data = $record->data;

        return [
            'retailer_external_id' => $data['retailer_external_id'] ?? null,
            'retailer_sku' => $data['retailer_sku'] ?? null,
            'gtin' => $data['gtin'] ?? null,
            'manufacturer_style_code' => $data['manufacturer_style_code'] ?? null,
            'raw_title' => $data['title'],
            'raw_colour' => $data['colour'],
            'product_url' => $data['product_url'],
            'affiliate_url' => $data['affiliate_url'] ?? null,
            'source_type' => ListingSourceType::Feed->value,
            'raw_payload' => $record->raw,
            'current_price' => $data['current_price'],
            'original_price' => $data['original_price'] ?? null,
            'currency' => $data['currency'],
            'delivery_cost' => $data['delivery']['cost'] ?? null,
            'delivery_min_days' => $data['delivery']['min_days'] ?? null,
            'delivery_max_days' => $data['delivery']['max_days'] ?? null,
            'delivery_note_lv' => $data['delivery']['note_lv'] ?? null,
            'delivery_note_en' => $data['delivery']['note_en'] ?? null,
            'active' => $data['active'],
            'last_checked_at' => CarbonImmutable::parse($data['observed_at']),
        ];
    }

    private function syncSizes(RetailerListing $listing, FeedRecord $record): void
    {
        $sizesByLabel = Size::query()
            ->whereIn('label', array_column($record->data['sizes'], 'eu_size'))
            ->pluck('id', 'label');
        $keptIds = [];

        foreach ($record->data['sizes'] as $sizeData) {
            $sizeId = $sizesByLabel[$sizeData['eu_size']];
            $keptIds[] = $sizeId;
            $listing->listingSizes()->updateOrCreate(
                ['size_id' => $sizeId],
                [
                    'in_stock' => $sizeData['in_stock'],
                    'price' => $sizeData['price'],
                ],
            );
        }

        $removedSizes = $listing->listingSizes();

        if ($keptIds !== []) {
            $removedSizes->whereNotIn('size_id', $keptIds);
        }

        $removedSizes->delete();
    }

    private function hasBusinessChanges(
        RetailerListing $listing,
        FeedRecord $record,
    ): bool {
        $attributes = $this->listingAttributes($record);
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
