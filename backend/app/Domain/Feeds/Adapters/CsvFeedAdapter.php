<?php

namespace App\Domain\Feeds\Adapters;

use App\Domain\Feeds\Contracts\FeedAdapter;
use App\Domain\Feeds\Data\FeedIssue;
use App\Domain\Feeds\Data\FeedReadResult;
use App\Domain\Feeds\Data\FeedRecord;

class CsvFeedAdapter implements FeedAdapter
{
    public function read(string $path): FeedReadResult
    {
        $stream = fopen($path, 'rb');

        if ($stream === false) {
            return new FeedReadResult([], [
                new FeedIssue(null, null, 'file_unreadable', 'Feed file could not be opened.'),
            ]);
        }

        $header = fgetcsv($stream, separator: ';', escape: '');

        if ($header === false) {
            fclose($stream);

            return new FeedReadResult([], [
                new FeedIssue(null, null, 'syntax_error', 'CSV header is missing.'),
            ]);
        }

        $records = [];
        $issues = [];
        $sourceRecord = 1;

        while (($row = fgetcsv($stream, separator: ';', escape: '')) !== false) {
            $sourceRecord++;

            if (count($row) !== count($header)) {
                $issues[] = new FeedIssue(
                    $sourceRecord,
                    null,
                    'syntax_error',
                    'CSV row does not match the header.',
                );

                continue;
            }

            $raw = array_combine($header, $row);
            $records[] = new FeedRecord(
                $sourceRecord,
                $this->normalize($raw),
                $raw,
            );
        }

        fclose($stream);

        return new FeedReadResult($records, $issues);
    }

    private function normalize(array $raw): array
    {
        return [
            'retailer_external_id' => $this->nullable($raw['external_id']),
            'retailer_sku' => $this->nullable($raw['sku']),
            'gtin' => $this->nullable($raw['gtin']),
            'manufacturer_style_code' => $this->nullable($raw['style_code']),
            'manufacturer_variant_code' => $this->nullable($raw['variant_code']),
            'brand' => $this->nullable($raw['brand']),
            'title' => $this->nullable($raw['title']),
            'colour' => $this->nullable($raw['colour']),
            'product_url' => $this->nullable($raw['product_url']),
            'affiliate_url' => $this->nullable($raw['affiliate_url']),
            'current_price' => $this->decimal($raw['price']),
            'original_price' => $this->decimal($raw['original_price']),
            'currency' => $this->nullable($raw['currency']),
            'delivery' => [
                'cost' => $this->decimal($raw['delivery_cost']),
                'min_days' => $this->integer($raw['delivery_min_days']),
                'max_days' => $this->integer($raw['delivery_max_days']),
                'note_lv' => $this->nullable($raw['delivery_note_lv']),
                'note_en' => $this->nullable($raw['delivery_note_en']),
            ],
            'images' => $this->pipeValues($raw['images']),
            'sizes' => $this->sizes($raw['sizes']),
            'active' => match ($raw['active']) {
                '1' => true,
                '0' => false,
                default => $raw['active'],
            },
            'observed_at' => $this->nullable($raw['observed_at']),
        ];
    }

    private function sizes(string $value): array
    {
        return array_map(function (string $encoded): array {
            [$size, $stock, $price] = array_pad(explode(':', $encoded, 3), 3, '');

            return [
                'eu_size' => $size,
                'in_stock' => match ($stock) {
                    '1' => true,
                    '0' => false,
                    default => $stock,
                },
                'price' => $this->decimal($price),
            ];
        }, $this->pipeValues($value));
    }

    private function pipeValues(string $value): array
    {
        return $value === ''
            ? []
            : array_values(array_filter(explode('|', $value), fn (string $item): bool => $item !== ''));
    }

    private function nullable(mixed $value): mixed
    {
        return $value === '' ? null : $value;
    }

    private function decimal(mixed $value): ?string
    {
        $value = $this->nullable($value);

        return $value === null ? null : str_replace(',', '.', (string) $value);
    }

    private function integer(mixed $value): mixed
    {
        $value = $this->nullable($value);

        return $value !== null && ctype_digit((string) $value)
            ? (int) $value
            : $value;
    }
}
