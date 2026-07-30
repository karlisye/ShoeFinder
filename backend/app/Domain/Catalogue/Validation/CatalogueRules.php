<?php

namespace App\Domain\Catalogue\Validation;

use App\Enums\Audience;
use App\Enums\ImageSourceType;
use App\Enums\ListingSourceType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Colour;
use App\Models\Retailer;
use App\Models\RetailerListing;
use App\Models\RetailerListingSize;
use App\Models\Shoe;
use App\Models\ShoeImage;
use App\Models\ShoeVariant;
use App\Models\Size;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

final class CatalogueRules
{
    public function brand(?Brand $brand = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $this->unique('brands', 'slug', $brand),
            ],
            'website_url' => ['nullable', 'url:http,https'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function category(?Category $category = null): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $this->unique('categories', 'slug', $category),
            ],
            'name_lv' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'description_lv' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:32767'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function colour(?Colour $colour = null): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $this->unique('colours', 'code', $colour),
            ],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:32767'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function size(?Size $size = null): array
    {
        return [
            'eu_size' => [
                'required',
                'numeric',
                'decimal:0,1',
                'min:0',
                $this->unique('sizes', 'eu_size', $size),
            ],
            'label' => [
                'required',
                'string',
                'max:8',
                $this->unique('sizes', 'label', $size),
            ],
            'sort_order' => [
                'required',
                'integer',
                'min:0',
                'max:32767',
                $this->unique('sizes', 'sort_order', $size),
            ],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function retailer(?Retailer $retailer = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $this->unique('retailers', 'slug', $retailer),
            ],
            'website_url' => ['nullable', 'url:http,https'],
            'logo_path' => ['nullable', 'string', 'max:2048'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function shoe(array $data, ?Shoe $shoe = null): array
    {
        $brandId = $data['brand_id'] ?? $shoe?->brand_id;
        $styleCode = $this->unique('shoes', 'manufacturer_style_code', $shoe);

        if ($brandId !== null) {
            $styleCode->where(
                fn (Builder $query): Builder => $query->where(
                    'brand_id',
                    $brandId,
                ),
            );
        }

        return [
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $this->unique('shoes', 'slug', $shoe),
            ],
            'manufacturer_style_code' => [
                'nullable',
                'string',
                'max:100',
                $styleCode,
            ],
            'audience' => ['required', Rule::enum(Audience::class)],
            'description_lv' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function variant(
        array $data,
        ?ShoeVariant $variant = null,
    ): array {
        $shoeId = $data['shoe_id'] ?? $variant?->shoe_id;
        $colour = $this->unique('shoe_variants', 'colour_id', $variant);
        $variantCode = $this->unique(
            'shoe_variants',
            'manufacturer_variant_code',
            $variant,
        );

        if ($shoeId !== null) {
            $colour->where(
                fn (Builder $query): Builder => $query->where(
                    'shoe_id',
                    $shoeId,
                ),
            );
            $variantCode->where(
                fn (Builder $query): Builder => $query->where(
                    'shoe_id',
                    $shoeId,
                ),
            );
        }

        return [
            'shoe_id' => ['required', 'integer', 'exists:shoes,id'],
            'colour_id' => [
                'required',
                'integer',
                'exists:colours,id',
                $colour,
            ],
            'manufacturer_variant_code' => [
                'nullable',
                'string',
                'max:100',
                $variantCode,
            ],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function image(array $data, ?ShoeImage $image = null): array
    {
        $variantId = $data['shoe_variant_id'] ?? $image?->shoe_variant_id;
        $sortOrder = $this->unique('shoe_images', 'sort_order', $image);

        if ($variantId !== null) {
            $sortOrder->where(
                fn (Builder $query): Builder => $query->where(
                    'shoe_variant_id',
                    $variantId,
                ),
            );
        }

        return [
            'shoe_variant_id' => [
                'required',
                'integer',
                'exists:shoe_variants,id',
            ],
            'source_type' => [
                'required',
                Rule::enum(ImageSourceType::class),
            ],
            'path' => [
                'nullable',
                'required_if:source_type,local',
                'prohibited_unless:source_type,local',
                'string',
                'max:2048',
            ],
            'external_url' => [
                'nullable',
                'required_if:source_type,external',
                'prohibited_unless:source_type,external',
                'url:https',
            ],
            'alt_text_lv' => ['nullable', 'string'],
            'alt_text_en' => ['nullable', 'string'],
            'sort_order' => [
                'required',
                'integer',
                'min:0',
                'max:32767',
                $sortOrder,
            ],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }

    public function listing(
        array $data,
        ?RetailerListing $listing = null,
    ): array {
        $retailerId = $data['retailer_id'] ?? $listing?->retailer_id;
        $variantId = $data['shoe_variant_id'] ?? $listing?->shoe_variant_id;
        $retailer = $this->unique(
            'retailer_listings',
            'retailer_id',
            $listing,
        );
        $externalId = $this->unique(
            'retailer_listings',
            'retailer_external_id',
            $listing,
        );
        $sku = $this->unique(
            'retailer_listings',
            'retailer_sku',
            $listing,
        );

        if ($variantId !== null) {
            $retailer->where(
                fn (Builder $query): Builder => $query->where(
                    'shoe_variant_id',
                    $variantId,
                ),
            );
        }

        if ($retailerId !== null) {
            $externalId->where(
                fn (Builder $query): Builder => $query->where(
                    'retailer_id',
                    $retailerId,
                ),
            );
            $sku->where(
                fn (Builder $query): Builder => $query->where(
                    'retailer_id',
                    $retailerId,
                ),
            );
        }

        $deliveryMaxDays = [
            'nullable',
            'integer',
            'min:0',
            'max:32767',
        ];

        if (($data['delivery_min_days'] ?? null) !== null) {
            $deliveryMaxDays[] = 'gte:delivery_min_days';
        }

        return [
            'shoe_variant_id' => [
                'required',
                'integer',
                'exists:shoe_variants,id',
            ],
            'retailer_id' => [
                'required',
                'integer',
                'exists:retailers,id',
                $retailer,
            ],
            'product_url' => ['required', 'url:http,https'],
            'affiliate_url' => ['nullable', 'url:http,https'],
            'retailer_external_id' => [
                'nullable',
                'string',
                'max:191',
                $externalId,
            ],
            'retailer_sku' => ['nullable', 'string', 'max:191', $sku],
            'gtin' => [
                'nullable',
                'regex:/^(?:[0-9]{8}|[0-9]{12}|[0-9]{13}|[0-9]{14})$/',
            ],
            'manufacturer_style_code' => ['nullable', 'string', 'max:100'],
            'raw_title' => ['nullable', 'string'],
            'raw_colour' => ['nullable', 'string', 'max:255'],
            'source_type' => [
                'required',
                Rule::enum(ListingSourceType::class),
            ],
            'raw_payload' => ['nullable', 'array'],
            'current_price' => [
                'required',
                'numeric',
                'decimal:0,2',
                'min:0',
            ],
            'original_price' => [
                'nullable',
                'numeric',
                'decimal:0,2',
                'min:0',
                'gte:current_price',
            ],
            'currency' => [
                'required',
                'string',
                'size:3',
                'uppercase',
                'regex:/^[A-Z]{3}$/',
            ],
            'delivery_cost' => [
                'nullable',
                'numeric',
                'decimal:0,2',
                'min:0',
            ],
            'delivery_min_days' => [
                'nullable',
                'integer',
                'min:0',
                'max:32767',
            ],
            'delivery_max_days' => $deliveryMaxDays,
            'delivery_note_lv' => ['nullable', 'string'],
            'delivery_note_en' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
            'last_checked_at' => ['nullable', 'date'],
        ];
    }

    public function listingSize(
        array $data,
        ?RetailerListingSize $listingSize = null,
    ): array {
        $listingId = $data['retailer_listing_id']
            ?? $listingSize?->retailer_listing_id;
        $size = $this->unique(
            'retailer_listing_sizes',
            'size_id',
            $listingSize,
        );

        if ($listingId !== null) {
            $size->where(
                fn (Builder $query): Builder => $query->where(
                    'retailer_listing_id',
                    $listingId,
                ),
            );
        }

        return [
            'retailer_listing_id' => [
                'required',
                'integer',
                'exists:retailer_listings,id',
            ],
            'size_id' => [
                'required',
                'integer',
                'exists:sizes,id',
                $size,
            ],
            'in_stock' => ['required', 'boolean'],
            'price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
        ];
    }

    private function unique(
        string $table,
        string $column,
        ?Model $model,
    ): Unique {
        $rule = Rule::unique($table, $column);

        return $model === null ? $rule : $rule->ignore($model);
    }
}
