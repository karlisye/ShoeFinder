<?php

namespace App\Domain\Feeds;

use App\Models\FeedImport;
use App\Models\FeedImportItem;
use App\Models\RetailerListing;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

class FeedImportChangePreview
{
    public function build(FeedImportItem $item): array
    {
        $item->loadMissing([
            'feedImport',
            'matchedListing.variant',
            'matchedListing.listingSizes.size',
        ]);

        $listing = $item->matchedListing;
        $writeStatus = $this->writeStatus($item);

        if (! $writeStatus['will_apply']) {
            return [
                ...$writeStatus,
                'fields' => [],
                'sizes' => [],
            ];
        }

        $data = $item->normalized_payload ?? [];

        return [
            ...$writeStatus,
            'fields' => $this->fieldChanges($item, $listing, $data),
            'sizes' => $this->sizeChanges($listing, $data),
        ];
    }

    private function writeStatus(FeedImportItem $item): array
    {
        if ($item->feedImport->status === FeedImport::STATUS_APPLIED) {
            return [
                'will_apply' => false,
                'summary' => 'This import has already been applied. The previous stored values are no longer available.',
            ];
        }

        if ($item->outcome === 'invalid') {
            return [
                'will_apply' => false,
                'summary' => 'No catalogue changes will be made because this source record is invalid.',
            ];
        }

        if ($item->outcome === 'missing') {
            return [
                'will_apply' => false,
                'summary' => 'No catalogue changes will be made. Missing listings are reported but not changed automatically.',
            ];
        }

        if ($item->outcome === 'manual_review' && $item->resolution === null) {
            return [
                'will_apply' => false,
                'summary' => 'No catalogue changes are planned until this row has a review decision.',
            ];
        }

        if ($item->resolution === FeedImportItem::RESOLUTION_IGNORE) {
            return [
                'will_apply' => false,
                'summary' => 'No catalogue changes will be made because this row is ignored.',
            ];
        }

        if ($item->feedImport->status !== FeedImport::STATUS_READY) {
            return [
                'will_apply' => false,
                'summary' => 'No catalogue changes are currently planned for this row.',
            ];
        }

        return [
            'will_apply' => true,
            'summary' => $item->matched_listing_id === null
                ? 'A new retailer listing will be created when this import is applied.'
                : 'The following stored listing values will change when this import is applied.',
        ];
    }

    private function fieldChanges(
        FeedImportItem $item,
        ?RetailerListing $listing,
        array $data,
    ): array {
        $delivery = is_array($data['delivery'] ?? null)
            ? $data['delivery']
            : [];
        $currency = $data['currency'] ?? $listing?->currency;
        $definitions = [
            $this->field(
                'Retailer external ID',
                $listing?->retailer_external_id,
                $data['retailer_external_id'] ?? null,
            ),
            $this->field(
                'Retailer SKU',
                $listing?->retailer_sku,
                $data['retailer_sku'] ?? null,
            ),
            $this->field(
                'GTIN / EAN',
                $listing?->gtin,
                $data['gtin'] ?? null,
            ),
            $this->field(
                'Manufacturer style code',
                $listing?->manufacturer_style_code,
                $data['manufacturer_style_code'] ?? null,
            ),
            $this->field(
                'Retailer title',
                $listing?->raw_title,
                $data['title'] ?? null,
            ),
            $this->field(
                'Retailer colour',
                $listing?->raw_colour,
                $data['colour'] ?? null,
            ),
            $this->field(
                'Product URL',
                $listing?->product_url,
                $data['product_url'] ?? null,
            ),
            $this->field(
                'Affiliate URL',
                $listing?->affiliate_url,
                $data['affiliate_url'] ?? null,
            ),
            $this->field(
                'Current price',
                $listing?->current_price,
                $data['current_price'] ?? null,
                $this->money($listing?->current_price, $listing?->currency),
                $this->money($data['current_price'] ?? null, $currency),
            ),
            $this->field(
                'Original price',
                $listing?->original_price,
                $data['original_price'] ?? null,
                $this->money($listing?->original_price, $listing?->currency),
                $this->money($data['original_price'] ?? null, $currency),
            ),
            $this->field(
                'Currency',
                $listing?->currency,
                $data['currency'] ?? null,
            ),
            $this->field(
                'Delivery cost',
                $listing?->delivery_cost,
                $delivery['cost'] ?? null,
                $this->money($listing?->delivery_cost, $listing?->currency),
                $this->money($delivery['cost'] ?? null, $currency),
            ),
            $this->field(
                'Minimum delivery days',
                $listing?->delivery_min_days,
                $delivery['min_days'] ?? null,
            ),
            $this->field(
                'Maximum delivery days',
                $listing?->delivery_max_days,
                $delivery['max_days'] ?? null,
            ),
            $this->field(
                'Latvian delivery note',
                $listing?->delivery_note_lv,
                $delivery['note_lv'] ?? null,
            ),
            $this->field(
                'English delivery note',
                $listing?->delivery_note_en,
                $delivery['note_en'] ?? null,
            ),
            $this->field(
                'Active',
                $listing?->active,
                $data['active'] ?? null,
                $this->boolean($listing?->active),
                $this->boolean($data['active'] ?? null),
            ),
            $this->field(
                'Source type',
                $listing?->source_type?->value,
                'feed',
                $listing?->source_type?->value,
                'feed',
            ),
            $this->field(
                'Last checked',
                $this->date($listing?->last_checked_at),
                $this->date($data['observed_at'] ?? null),
                $this->date($listing?->last_checked_at),
                $this->date($data['observed_at'] ?? null),
            ),
        ];

        if ($item->resolution === FeedImportItem::RESOLUTION_UPDATE_MATCHED) {
            $definitions[] = $this->field(
                'Manufacturer variant code',
                $listing?->variant?->manufacturer_variant_code,
                $data['manufacturer_variant_code'] ?? null,
            );
        }

        return array_values(array_filter($definitions));
    }

    private function sizeChanges(
        ?RetailerListing $listing,
        array $data,
    ): array {
        $existing = $listing?->listingSizes
            ->mapWithKeys(fn ($listingSize): array => [
                $listingSize->size->label => [
                    'in_stock' => $listingSize->in_stock,
                    'price' => $listingSize->price,
                ],
            ])
            ->all() ?? [];
        $incoming = collect(
            is_array($data['sizes'] ?? null) ? $data['sizes'] : [],
        )
            ->filter(fn (mixed $size): bool => is_array($size)
                && filled($size['eu_size'] ?? null))
            ->mapWithKeys(fn (array $size): array => [
                (string) $size['eu_size'] => [
                    'in_stock' => $size['in_stock'] ?? null,
                    'price' => $size['price'] ?? null,
                ],
            ])
            ->all();
        $labels = array_values(array_unique([
            ...array_keys($existing),
            ...array_keys($incoming),
        ]));
        usort($labels, strnatcasecmp(...));
        $currency = $data['currency'] ?? $listing?->currency;

        return collect($labels)
            ->map(function (string $label) use (
                $existing,
                $incoming,
                $currency,
            ): ?array {
                $current = $existing[$label] ?? null;
                $next = $incoming[$label] ?? null;

                if ($this->sameSize($current, $next)) {
                    return null;
                }

                return [
                    'label' => "EU {$label}",
                    'current' => $this->size($current, $currency),
                    'incoming' => $this->size($next, $currency),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function field(
        string $label,
        mixed $current,
        mixed $incoming,
        ?string $currentDisplay = null,
        ?string $incomingDisplay = null,
    ): ?array {
        if ($this->same($current, $incoming)) {
            return null;
        }

        return [
            'label' => $label,
            'current' => $currentDisplay ?? $this->display($current),
            'incoming' => $incomingDisplay ?? $this->display($incoming),
        ];
    }

    private function same(mixed $current, mixed $incoming): bool
    {
        if ($current instanceof DateTimeInterface) {
            $current = $current->getTimestamp();
        }

        if ($incoming instanceof DateTimeInterface) {
            $incoming = $incoming->getTimestamp();
        }

        if ($current === null || $incoming === null) {
            return $current === $incoming;
        }

        if (is_bool($current) || is_bool($incoming)) {
            return (bool) $current === (bool) $incoming;
        }

        return (string) $current === (string) $incoming;
    }

    private function sameSize(?array $current, ?array $incoming): bool
    {
        if ($current === null || $incoming === null) {
            return $current === $incoming;
        }

        return (bool) $current['in_stock'] === (bool) $incoming['in_stock']
            && $this->same($current['price'], $incoming['price']);
    }

    private function display(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Not provided';
        }

        if ($value instanceof DateTimeInterface) {
            return $this->date($value) ?? 'Not provided';
        }

        return (string) $value;
    }

    private function money(mixed $value, ?string $currency): string
    {
        if ($value === null || $value === '') {
            return 'Not provided';
        }

        return number_format((float) $value, 2, '.', '')
            .(filled($currency) ? " {$currency}" : '');
    }

    private function boolean(mixed $value): string
    {
        if ($value === null) {
            return 'Not provided';
        }

        return (bool) $value ? 'Yes' : 'No';
    }

    private function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)
                ->utc()
                ->format('Y-m-d H:i \U\T\C');
        } catch (Throwable) {
            return (string) $value;
        }
    }

    private function size(?array $size, ?string $currency): string
    {
        if ($size === null) {
            return 'Not listed';
        }

        $stock = (bool) $size['in_stock'] ? 'In stock' : 'Out of stock';
        $price = $size['price'] === null
            ? 'listing price'
            : $this->money($size['price'], $currency);

        return "{$stock}, {$price}";
    }
}
