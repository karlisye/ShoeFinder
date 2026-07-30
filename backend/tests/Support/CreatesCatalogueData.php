<?php

namespace Tests\Support;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Colour;
use App\Models\Retailer;
use App\Models\RetailerListing;
use App\Models\RetailerListingSize;
use App\Models\Shoe;
use App\Models\ShoeVariant;
use App\Models\Size;
use Carbon\CarbonImmutable;

trait CreatesCatalogueData
{
    protected function createCatalogueContext(
        string $suffix = 'default',
    ): array {
        $brand = Brand::create([
            'name' => "Brand {$suffix}",
            'slug' => "brand-{$suffix}",
        ]);
        $category = Category::create([
            'slug' => "category-{$suffix}",
            'name_lv' => "Kategorija {$suffix}",
            'name_en' => "Category {$suffix}",
        ]);
        $colour = Colour::create([
            'code' => "colour-{$suffix}",
            'name' => "Colour {$suffix}",
        ]);
        $size = Size::create([
            'eu_size' => 42,
            'label' => '42',
            'sort_order' => 1,
        ]);
        $shoe = Shoe::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name' => "Shoe {$suffix}",
            'slug' => "shoe-{$suffix}",
            'manufacturer_style_code' => "STYLE-{$suffix}",
            'audience' => 'unisex',
        ]);
        $variant = ShoeVariant::create([
            'shoe_id' => $shoe->id,
            'colour_id' => $colour->id,
            'manufacturer_variant_code' => "VARIANT-{$suffix}",
        ]);
        $retailer = $this->createRetailer($suffix);

        return compact(
            'brand',
            'category',
            'colour',
            'size',
            'shoe',
            'variant',
            'retailer',
        );
    }

    protected function createRetailer(string $suffix): Retailer
    {
        return Retailer::create([
            'name' => "Retailer {$suffix}",
            'slug' => "retailer-{$suffix}",
            'website_url' => "https://{$suffix}.retailer.example",
        ]);
    }

    protected function createSize(
        string $label,
        float $euSize,
        int $sortOrder,
    ): Size {
        return Size::create([
            'eu_size' => $euSize,
            'label' => $label,
            'sort_order' => $sortOrder,
        ]);
    }

    protected function createVariant(
        Shoe $shoe,
        string $suffix,
    ): ShoeVariant {
        $colour = Colour::create([
            'code' => "colour-{$suffix}",
            'name' => "Colour {$suffix}",
        ]);

        return ShoeVariant::create([
            'shoe_id' => $shoe->id,
            'colour_id' => $colour->id,
            'manufacturer_variant_code' => "VARIANT-{$suffix}",
        ]);
    }

    protected function createListing(
        ShoeVariant $variant,
        Retailer $retailer,
        array $overrides = [],
    ): RetailerListing {
        return RetailerListing::create([
            ...$this->listingPayload($variant, $retailer),
            ...$overrides,
        ]);
    }

    protected function listingPayload(
        ShoeVariant $variant,
        Retailer $retailer,
        array $overrides = [],
    ): array {
        return [
            'shoe_variant_id' => $variant->id,
            'retailer_id' => $retailer->id,
            'product_url' => "https://{$retailer->slug}.example/shoe",
            'affiliate_url' => "https://{$retailer->slug}.example/go/shoe",
            'retailer_external_id' => "external-{$variant->id}-{$retailer->id}",
            'retailer_sku' => "sku-{$variant->id}-{$retailer->id}",
            'gtin' => '1234567890123',
            'manufacturer_style_code' => 'STYLE-DEFAULT',
            'raw_title' => 'Raw retailer title',
            'raw_colour' => 'Black',
            'source_type' => 'manual',
            'raw_payload' => ['source' => 'test'],
            'current_price' => '99.99',
            'original_price' => '119.99',
            'currency' => 'EUR',
            'delivery_cost' => '3.99',
            'delivery_min_days' => 2,
            'delivery_max_days' => 4,
            'delivery_note_lv' => 'Piegādes piezīme',
            'delivery_note_en' => 'Delivery note',
            'active' => true,
            'last_checked_at' => CarbonImmutable::parse(
                '2026-07-27 12:00:00 UTC',
            ),
            ...$overrides,
        ];
    }

    protected function createListingSize(
        RetailerListing $listing,
        Size $size,
        array $overrides = [],
    ): RetailerListingSize {
        return RetailerListingSize::create([
            'retailer_listing_id' => $listing->id,
            'size_id' => $size->id,
            'in_stock' => true,
            'price' => null,
            ...$overrides,
        ]);
    }
}
