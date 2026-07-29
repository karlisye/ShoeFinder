<?php

namespace App\Domain\Feeds\Adapters;

use App\Domain\Feeds\Contracts\FeedAdapter;
use App\Domain\Feeds\Data\FeedIssue;
use App\Domain\Feeds\Data\FeedReadResult;
use App\Domain\Feeds\Data\FeedRecord;
use SimpleXMLElement;

class XmlFeedAdapter implements FeedAdapter
{
    public function read(string $path): FeedReadResult
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_file(
            $path,
            SimpleXMLElement::class,
            LIBXML_NONET,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $xml instanceof SimpleXMLElement) {
            return new FeedReadResult([], [
                new FeedIssue(null, null, 'syntax_error', 'XML feed is malformed.'),
            ]);
        }

        $xml->registerXPathNamespace('feed', 'https://feeds.shoefinder.example/v1');
        $products = $xml->xpath('/feed:catalogue/feed:product') ?: [];
        $records = [];

        foreach ($products as $index => $product) {
            $normalized = $this->normalize($product);
            $records[] = new FeedRecord(
                $index + 1,
                $normalized,
                ['xml' => $product->asXML()],
            );
        }

        return new FeedReadResult($records);
    }

    private function normalize(SimpleXMLElement $product): array
    {
        $delivery = $product->delivery;
        $price = $product->price;
        $images = [];
        $sizes = [];

        foreach ($product->images->image as $image) {
            $images[] = (string) $image;
        }

        foreach ($product->sizes->size as $size) {
            $sizes[] = [
                'eu_size' => (string) $size['eu'],
                'in_stock' => match ((string) $size['in-stock']) {
                    'true', '1' => true,
                    'false', '0' => false,
                    default => (string) $size['in-stock'],
                },
                'price' => $this->attribute($size, 'price'),
            ];
        }

        return [
            'retailer_external_id' => $this->attribute($product, 'external-id'),
            'retailer_sku' => $this->element($product, 'sku'),
            'gtin' => $this->element($product, 'gtin'),
            'manufacturer_style_code' => $this->element($product, 'style-code'),
            'manufacturer_variant_code' => $this->element($product, 'variant-code'),
            'brand' => $this->element($product, 'brand'),
            'title' => $this->element($product, 'title'),
            'colour' => $this->element($product, 'colour'),
            'product_url' => $this->element($product, 'product-url'),
            'affiliate_url' => $this->element($product, 'affiliate-url'),
            'current_price' => $this->attribute($price, 'current'),
            'original_price' => $this->attribute($price, 'original'),
            'currency' => $this->attribute($price, 'currency'),
            'delivery' => [
                'cost' => $this->attribute($delivery, 'cost'),
                'min_days' => $this->integer($this->attribute($delivery, 'min-days')),
                'max_days' => $this->integer($this->attribute($delivery, 'max-days')),
                'note_lv' => $this->element($delivery, 'note-lv'),
                'note_en' => $this->element($delivery, 'note-en'),
            ],
            'images' => $images,
            'sizes' => $sizes,
            'active' => match ($this->element($product, 'active')) {
                'true', '1' => true,
                'false', '0' => false,
                default => $this->element($product, 'active'),
            },
            'observed_at' => $this->element($product, 'observed-at'),
        ];
    }

    private function element(SimpleXMLElement $element, string $name): ?string
    {
        $value = (string) $element->{$name};

        return $value === '' ? null : $value;
    }

    private function attribute(SimpleXMLElement $element, string $name): ?string
    {
        $value = (string) $element[$name];

        return $value === '' ? null : $value;
    }

    private function integer(?string $value): int|string|null
    {
        return $value !== null && ctype_digit($value) ? (int) $value : $value;
    }
}
