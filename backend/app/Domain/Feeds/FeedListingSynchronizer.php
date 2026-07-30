<?php

namespace App\Domain\Feeds;

use App\Domain\Feeds\Data\FeedRecord;
use App\Enums\ListingSourceType;
use App\Models\Retailer;
use App\Models\RetailerListing;
use App\Models\ShoeVariant;
use App\Models\Size;
use Carbon\CarbonImmutable;
use LogicException;

class FeedListingSynchronizer
{
    public function sync(
        Retailer $retailer,
        FeedRecord $record,
        ?RetailerListing $listing = null,
        ?ShoeVariant $variant = null,
    ): RetailerListing {
        if ($listing === null && $variant === null) {
            throw new LogicException('A listing or variant is required.');
        }

        $listing ??= new RetailerListing([
            'shoe_variant_id' => $variant->getKey(),
            'retailer_id' => $retailer->getKey(),
        ]);

        if ((int) $listing->retailer_id !== (int) $retailer->getKey()) {
            throw new LogicException('The listing belongs to a different retailer.');
        }

        $listing->fill($this->listingAttributes($record));
        $listing->save();
        $this->syncSizes($listing, $record);

        return $listing;
    }

    public function listingAttributes(FeedRecord $record): array
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
}
