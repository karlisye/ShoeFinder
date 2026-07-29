<?php

namespace App\Domain\Feeds;

use App\Domain\Feeds\Data\FeedIssue;
use App\Domain\Feeds\Data\FeedRecord;
use App\Models\Size;
use DateTimeImmutable;
use Throwable;

class FeedRecordValidator
{
    private ?array $knownSizes = null;

    private const FIELDS = [
        'retailer_external_id',
        'retailer_sku',
        'gtin',
        'manufacturer_style_code',
        'manufacturer_variant_code',
        'brand',
        'title',
        'colour',
        'product_url',
        'affiliate_url',
        'current_price',
        'original_price',
        'currency',
        'delivery',
        'images',
        'sizes',
        'active',
        'observed_at',
    ];

    /**
     * @return array<int, FeedIssue>
     */
    public function validate(FeedRecord $record): array
    {
        $data = $record->data;
        $issues = [];

        foreach (array_diff(array_keys($data), self::FIELDS) as $field) {
            $issues[] = $this->issue($record, $field, 'field_unknown', 'Unknown field.');
        }

        if (blank($data['retailer_external_id'] ?? null) && blank($data['retailer_sku'] ?? null)) {
            $issues[] = $this->issue(
                $record,
                'retailer_external_id',
                'identity_missing',
                'External ID or SKU is required.',
            );
        }

        foreach ([
            'manufacturer_variant_code',
            'brand',
            'title',
            'colour',
            'product_url',
            'currency',
            'observed_at',
        ] as $field) {
            if (blank($data[$field] ?? null)) {
                $issues[] = $this->issue($record, $field, 'required', 'Field is required.');
            }
        }

        if (filled($data['gtin'] ?? null) && ! $this->validGtin((string) $data['gtin'])) {
            $issues[] = $this->issue($record, 'gtin', 'gtin_invalid', 'GTIN is invalid.');
        }

        foreach (['product_url', 'affiliate_url'] as $field) {
            if (filled($data[$field] ?? null) && ! $this->validHttpsUrl((string) $data[$field])) {
                $issues[] = $this->issue($record, $field, 'url_invalid', 'HTTPS URL is required.');
            }
        }

        if (! $this->validMoney($data['current_price'] ?? null)) {
            $issues[] = $this->issue(
                $record,
                'current_price',
                $this->moneyIssueCode($data['current_price'] ?? null),
                'Current price must be a nonnegative decimal.',
            );
        }

        if (filled($data['original_price'] ?? null) && ! $this->validMoney($data['original_price'])) {
            $issues[] = $this->issue(
                $record,
                'original_price',
                $this->moneyIssueCode($data['original_price']),
                'Original price must be a nonnegative decimal.',
            );
        }

        if (
            is_numeric($data['current_price'] ?? null)
            && is_numeric($data['original_price'] ?? null)
            && (float) $data['original_price'] < (float) $data['current_price']
        ) {
            $issues[] = $this->issue(
                $record,
                'original_price',
                'original_price_below_current',
                'Original price cannot be below current price.',
            );
        }

        if (($data['currency'] ?? null) !== 'EUR') {
            $issues[] = $this->issue(
                $record,
                'currency',
                'currency_unsupported',
                'Only EUR is supported.',
            );
        }

        if (! is_bool($data['active'] ?? null)) {
            $issues[] = $this->issue($record, 'active', 'boolean_invalid', 'Active must be boolean.');
        }

        if (! $this->validDate($data['observed_at'] ?? null)) {
            $issues[] = $this->issue(
                $record,
                'observed_at',
                'date_invalid',
                'Observation time must be ISO 8601.',
            );
        }

        array_push($issues, ...$this->validateDelivery($record));
        array_push($issues, ...$this->validateImages($record));
        array_push($issues, ...$this->validateSizes($record));

        return $issues;
    }

    /**
     * @param  array<int, FeedRecord>  $records
     * @return array<int, array<int, FeedIssue>>
     */
    public function duplicateIssues(array $records): array
    {
        $issues = [];

        foreach (['retailer_external_id', 'retailer_sku'] as $field) {
            $seen = [];

            foreach ($records as $record) {
                $value = $record->data[$field] ?? null;

                if (blank($value)) {
                    continue;
                }

                if (isset($seen[$value])) {
                    $issues[$record->sourceRecord][] = $this->issue(
                        $record,
                        $field,
                        'identity_duplicate',
                        'Identity is duplicated in the feed.',
                    );
                    $issues[$seen[$value]->sourceRecord][] = $this->issue(
                        $seen[$value],
                        $field,
                        'identity_duplicate',
                        'Identity is duplicated in the feed.',
                    );
                } else {
                    $seen[$value] = $record;
                }
            }
        }

        $gtins = [];

        foreach ($records as $record) {
            $gtin = $record->data['gtin'] ?? null;

            if (blank($gtin)) {
                continue;
            }

            if (
                isset($gtins[$gtin])
                && $this->productIdentity($gtins[$gtin]) !== $this->productIdentity($record)
            ) {
                foreach ([$gtins[$gtin], $record] as $conflictingRecord) {
                    $issues[$conflictingRecord->sourceRecord][] = $this->issue(
                        $conflictingRecord,
                        'gtin',
                        'identity_conflict',
                        'GTIN identifies different products in the feed.',
                    );
                }
            } else {
                $gtins[$gtin] = $record;
            }
        }

        return $issues;
    }

    private function validateDelivery(FeedRecord $record): array
    {
        $delivery = $record->data['delivery'] ?? null;

        if (! is_array($delivery)) {
            return [$this->issue($record, 'delivery', 'required', 'Delivery object is required.')];
        }

        $issues = [];
        $cost = $delivery['cost'] ?? null;
        $minimum = $delivery['min_days'] ?? null;
        $maximum = $delivery['max_days'] ?? null;

        if ($cost !== null && ! $this->validMoney($cost)) {
            $issues[] = $this->issue(
                $record,
                'delivery.cost',
                $this->moneyIssueCode($cost),
                'Delivery cost must be a nonnegative decimal.',
            );
        }

        foreach (['min_days', 'max_days'] as $field) {
            $value = $delivery[$field] ?? null;

            if ($value !== null && (! is_int($value) || $value < 0 || $value > 32767)) {
                $issues[] = $this->issue(
                    $record,
                    "delivery.{$field}",
                    'integer_invalid',
                    'Delivery days must be a nonnegative integer.',
                );
            }
        }

        if (is_int($minimum) && is_int($maximum) && $maximum < $minimum) {
            $issues[] = $this->issue(
                $record,
                'delivery.max_days',
                'delivery_range_invalid',
                'Maximum delivery days cannot be below minimum delivery days.',
            );
        }

        return $issues;
    }

    private function validateImages(FeedRecord $record): array
    {
        $images = $record->data['images'] ?? null;

        if (! is_array($images)) {
            return [$this->issue($record, 'images', 'array_invalid', 'Images must be an array.')];
        }

        $issues = [];

        foreach ($images as $index => $image) {
            if (! is_string($image) || ! $this->validHttpsUrl($image)) {
                $issues[] = $this->issue(
                    $record,
                    "images.{$index}",
                    'url_invalid',
                    'Image must use an HTTPS URL.',
                );
            }
        }

        return $issues;
    }

    private function validateSizes(FeedRecord $record): array
    {
        $sizes = $record->data['sizes'] ?? null;

        if (! is_array($sizes)) {
            return [$this->issue($record, 'sizes', 'array_invalid', 'Sizes must be an array.')];
        }

        $issues = [];
        $seen = [];
        $knownSizes = $this->knownSizes();

        foreach ($sizes as $index => $size) {
            if (! is_array($size)) {
                $issues[] = $this->issue(
                    $record,
                    "sizes.{$index}",
                    'array_invalid',
                    'Size must be an object.',
                );

                continue;
            }

            $label = (string) ($size['eu_size'] ?? '');

            if (! isset($knownSizes[$label])) {
                $issues[] = $this->issue(
                    $record,
                    "sizes.{$index}.eu_size",
                    'size_unknown',
                    'EU size is not in the reference table.',
                );
            }

            if (isset($seen[$label])) {
                $issues[] = $this->issue(
                    $record,
                    "sizes.{$index}.eu_size",
                    'size_duplicate',
                    'EU size is duplicated in the listing.',
                );
            }
            $seen[$label] = true;

            if (! is_bool($size['in_stock'] ?? null)) {
                $issues[] = $this->issue(
                    $record,
                    "sizes.{$index}.in_stock",
                    'boolean_invalid',
                    'Size stock must be boolean.',
                );
            }

            if (($size['price'] ?? null) !== null && ! $this->validMoney($size['price'])) {
                $issues[] = $this->issue(
                    $record,
                    "sizes.{$index}.price",
                    $this->moneyIssueCode($size['price']),
                    'Size price must be a nonnegative decimal.',
                );
            }
        }

        return $issues;
    }

    private function validMoney(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^(?:0|[1-9][0-9]*)\.[0-9]{2}$/', $value) === 1;
    }

    private function moneyIssueCode(mixed $value): string
    {
        return is_numeric($value) && (float) $value < 0
            ? 'money_negative'
            : 'money_invalid';
    }

    private function knownSizes(): array
    {
        return $this->knownSizes ??= Size::query()
            ->where('active', true)
            ->pluck('label')
            ->mapWithKeys(fn (string $label): array => [$label => true])
            ->all();
    }

    private function productIdentity(FeedRecord $record): string
    {
        return implode('|', [
            mb_strtolower((string) ($record->data['brand'] ?? '')),
            mb_strtolower((string) ($record->data['manufacturer_variant_code'] ?? '')),
            mb_strtolower((string) ($record->data['title'] ?? '')),
        ]);
    }

    private function validHttpsUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && parse_url($url, PHP_URL_SCHEME) === 'https';
    }

    private function validDate(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        try {
            new DateTimeImmutable($value);

            return str_contains($value, 'T');
        } catch (Throwable) {
            return false;
        }
    }

    private function validGtin(string $gtin): bool
    {
        if (preg_match('/^(?:[0-9]{8}|[0-9]{12}|[0-9]{13}|[0-9]{14})$/', $gtin) !== 1) {
            return false;
        }

        $digits = array_map('intval', str_split($gtin));
        $checkDigit = array_pop($digits);
        $sum = 0;
        $weight = count($digits) % 2 === 0 ? 1 : 3;

        foreach ($digits as $digit) {
            $sum += $digit * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }

        return $checkDigit === (10 - ($sum % 10)) % 10;
    }

    private function issue(
        FeedRecord $record,
        ?string $field,
        string $code,
        string $message,
    ): FeedIssue {
        return new FeedIssue($record->sourceRecord, $field, $code, $message);
    }
}
