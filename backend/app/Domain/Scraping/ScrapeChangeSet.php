<?php

namespace App\Domain\Scraping;

class ScrapeChangeSet
{
    /** @return array<string, mixed> */
    public function build(array $baseline, array $result): array
    {
        $after = $result['availability'] === 'available'
            ? [
                'current_price' => $result['current_price'],
                'original_price' => $result['original_price'],
                'currency' => $result['currency'],
                'active' => true,
                'sizes' => array_map(fn (array $size): array => [
                    ...$size,
                    'price' => null,
                ], $result['sizes']),
            ]
            : [
                'current_price' => $baseline['current_price'],
                'original_price' => $baseline['original_price'],
                'currency' => $baseline['currency'],
                'active' => false,
                'sizes' => array_map(fn (array $size): array => [
                    ...$size,
                    'in_stock' => false,
                ], $baseline['sizes']),
            ];

        $listing = [];
        foreach (['current_price', 'original_price', 'currency', 'active'] as $field) {
            if ($baseline[$field] !== $after[$field]) {
                $listing[$field] = [
                    'before' => $baseline[$field],
                    'after' => $after[$field],
                ];
            }
        }

        $sizes = $this->canonicalSizes($baseline['sizes']) === $this->canonicalSizes($after['sizes'])
            ? []
            : [
                'before' => $baseline['sizes'],
                'after' => $after['sizes'],
            ];

        return array_filter([
            'listing' => $listing,
            'sizes' => $sizes,
        ]);
    }

    private function canonicalSizes(array $sizes): array
    {
        $sortedSizes = collect($sizes)
            ->sortBy(fn (array $size): float => (float) $size['eu_size'])
            ->values()
            ->all();

        return $this->canonicalize($sortedSizes);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function canonicalize(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(
            fn (mixed $item): mixed => is_array($item)
                ? $this->canonicalize($item)
                : $item,
            $value,
        );
    }
}
