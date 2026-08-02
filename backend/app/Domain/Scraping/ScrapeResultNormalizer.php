<?php

namespace App\Domain\Scraping;

use App\Models\ScrapeRunItem;
use App\Models\Size;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ScrapeResultNormalizer
{
    /** @return array<string, mixed> */
    public function normalize(ScrapeRunItem $item, array $payload): array
    {
        if (($payload['schema_version'] ?? null) !== 1) {
            throw new RuntimeException('The scraper result uses an unsupported schema version.');
        }

        if (($payload['ok'] ?? null) !== true) {
            $error = is_array($payload['error'] ?? null) ? $payload['error'] : [];

            throw new ScrapeResultException(
                (string) ($error['code'] ?? 'scrape_failed'),
                (string) ($error['message'] ?? 'The product page could not be scraped.'),
                (bool) ($error['retryable'] ?? false),
            );
        }

        $validator = Validator::make($payload, [
            'request_id' => ['required', 'string', Rule::in([(string) $item->getKey()])],
            'requested_url' => ['required', 'url:https', Rule::in([$item->product_url])],
            'final_url' => ['required', 'url:https'],
            'observed_at' => ['required', 'date'],
            'availability' => ['required', Rule::in(['available', 'unavailable'])],
            'current_price' => ['nullable', 'numeric', 'min:0', 'required_if:availability,available'],
            'original_price' => ['nullable', 'numeric', 'min:0', 'gte:current_price'],
            'currency' => ['nullable', 'required_if:availability,available', Rule::in(['EUR'])],
            'sku' => ['nullable', 'string', 'max:191'],
            'sizes' => ['present', 'array'],
            'sizes.*.eu_size' => ['required', 'string', 'distinct'],
            'sizes.*.in_stock' => ['required', 'boolean'],
        ]);

        try {
            $validated = $validator->validate();
        } catch (ValidationException $exception) {
            throw new ScrapeResultException(
                'result_invalid',
                Arr::first(Arr::flatten($exception->errors())) ?? 'The scraper result is invalid.',
            );
        }

        $this->validateFinalUrl($validated['final_url']);
        $this->validateSku($item, $validated['sku'] ?? null);
        $this->validateSizes($validated['sizes'], $validated['availability']);

        return [
            'availability' => $validated['availability'],
            'current_price' => isset($validated['current_price'])
                ? number_format((float) $validated['current_price'], 2, '.', '')
                : null,
            'original_price' => isset($validated['original_price'])
                ? number_format((float) $validated['original_price'], 2, '.', '')
                : null,
            'currency' => $validated['currency'] ?? null,
            'sku' => $validated['sku'] ?? null,
            'final_url' => $validated['final_url'],
            'observed_at' => CarbonImmutable::parse($validated['observed_at'])->toIso8601String(),
            'sizes' => collect($validated['sizes'])
                ->sortBy(fn (array $size): float => (float) $size['eu_size'])
                ->values()
                ->map(fn (array $size): array => [
                    'eu_size' => $size['eu_size'],
                    'in_stock' => (bool) $size['in_stock'],
                ])
                ->all(),
        ];
    }

    private function validateFinalUrl(string $url): void
    {
        $parts = parse_url($url);
        $path = (string) ($parts['path'] ?? '');

        if (($parts['scheme'] ?? null) !== 'https'
            || ($parts['host'] ?? null) !== 'ballzy.eu'
            || isset($parts['port'])
            || ! str_starts_with($path, '/en/product/') && ! str_starts_with($path, '/lv/product/')) {
            throw new ScrapeResultException('url_not_allowed', 'The scraper followed an unsupported product URL.');
        }
    }

    private function validateSku(ScrapeRunItem $item, ?string $sku): void
    {
        $variantCode = $item->retailerListing?->variant?->manufacturer_variant_code;

        if ($sku === null || $variantCode === null) {
            return;
        }

        $normalizedSku = preg_replace('/[^A-Z0-9]/', '', strtoupper($sku));
        $normalizedVariant = preg_replace('/[^A-Z0-9]/', '', strtoupper($variantCode));

        if (! str_starts_with($normalizedSku, $normalizedVariant)) {
            throw new ScrapeResultException('sku_mismatch', 'The scraped SKU does not match the catalogue variant.');
        }
    }

    /** @param array<int, array<string, mixed>> $sizes */
    private function validateSizes(array $sizes, string $availability): void
    {
        if ($availability === 'available' && $sizes === []) {
            throw new ScrapeResultException('sizes_missing', 'The available product has no size inventory.');
        }

        if ($availability === 'available'
            && ! collect($sizes)->contains(fn (array $size): bool => (bool) $size['in_stock'])) {
            throw new ScrapeResultException('stock_invalid', 'The available product has no in-stock size.');
        }

        $labels = array_column($sizes, 'eu_size');
        $knownCount = Size::query()->whereIn('label', $labels)->count();

        if ($knownCount !== count($labels)) {
            throw new ScrapeResultException('unknown_size', 'The scraper returned an unknown EU size.');
        }
    }
}
