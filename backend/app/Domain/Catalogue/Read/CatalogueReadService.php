<?php

namespace App\Domain\Catalogue\Read;

use App\Domain\Catalogue\Pricing\ListingFreshness;
use App\Domain\Catalogue\Pricing\LowestPrice;
use App\Domain\Catalogue\Pricing\LowestPriceFinder;
use App\Domain\Catalogue\Pricing\QualifyingListingSizeQuery;
use App\Enums\Audience;
use App\Enums\ImageSourceType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Colour;
use App\Models\FilterColour;
use App\Models\RetailerListing;
use App\Models\RetailerListingSize;
use App\Models\Shoe;
use App\Models\ShoeImage;
use App\Models\ShoeVariant;
use App\Models\Size;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final readonly class CatalogueReadService
{
    public function __construct(
        private QualifyingListingSizeQuery $qualifyingListingSizes,
        private LowestPriceFinder $lowestPriceFinder,
        private ListingFreshness $freshness,
    ) {}

    public function shoes(array $filters): array
    {
        $query = $this->publicShoesQuery()
            ->with([
                'brand',
                'category',
                'variants' => fn ($query) => $query
                    ->where('active', true)
                    ->whereHas(
                        'colour',
                        fn ($query) => $query
                            ->where('active', true),
                    ),
                'variants.colour',
                'variants.colour.filterColours',
                'variants.images' => fn ($query) => $query
                    ->orderByDesc('is_primary')
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ]);

        $this->applyShoeFilters($query, $filters);

        $cards = $query
            ->get()
            ->flatMap(fn (Shoe $shoe): Collection => $shoe->variants
                ->filter(
                    fn (ShoeVariant $variant): bool => $this
                        ->variantMatchesFilters($variant, $filters),
                )
                ->map(fn (ShoeVariant $variant): array => $this->shoeCard(
                    $shoe,
                    $variant,
                    $filters['locale'],
                    $filters['currency'],
                )))
            ->all();

        $this->sortCards($cards, $filters['sort']);

        $page = $filters['page'];
        $perPage = $filters['per_page'];
        $total = count($cards);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $items = array_slice($cards, ($page - 1) * $perPage, $perPage);

        foreach ($items as &$item) {
            unset($item['_created_at']);
        }
        unset($item);

        return [
            'data' => array_values($items),
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $items === [] ? null : (($page - 1) * $perPage) + 1,
                'to' => $items === []
                    ? null
                    : (($page - 1) * $perPage) + count($items),
                'currency' => $filters['currency'],
            ],
        ];
    }

    public function shoe(string $slug, array $options): ?array
    {
        $shoe = $this->publicShoesQuery()
            ->where('slug', $slug)
            ->with([
                'brand',
                'category',
                'variants' => fn ($query) => $query
                    ->where('active', true)
                    ->whereHas(
                        'colour',
                        fn ($query) => $query
                            ->where('active', true),
                    ),
                'variants.colour',
                'variants.colour.filterColours',
                'variants.images' => fn ($query) => $query
                    ->orderByDesc('is_primary')
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'variants.retailerListings' => fn ($query) => $query
                    ->where('active', true)
                    ->whereHas(
                        'retailer',
                        fn ($query) => $query
                            ->where('active', true),
                    ),
                'variants.retailerListings.retailer',
                'variants.retailerListings.listingSizes' => fn ($query) => $query
                    ->whereHas(
                        'size',
                        fn ($query) => $query
                            ->where('active', true),
                    ),
                'variants.retailerListings.listingSizes.size',
            ])
            ->first();

        if ($shoe === null) {
            return null;
        }

        $selectedSize = isset($options['size'])
            ? Size::query()
                ->where('label', $options['size'])
                ->where('active', true)
                ->first()
            : null;
        $lowestPrice = $this->lowestPriceFinder->forShoe(
            $shoe,
            $selectedSize,
            $options['currency'],
        );

        $variants = $shoe->variants
            ->sort(function (ShoeVariant $left, ShoeVariant $right): int {
                $sort = $left->colour->sort_order <=> $right->colour->sort_order;

                return $sort !== 0 ? $sort : ($left->id <=> $right->id);
            })
            ->values()
            ->map(fn (ShoeVariant $variant): array => $this->variant(
                $variant,
                $selectedSize,
                $options['locale'],
                $options['currency'],
            ))
            ->all();

        return [
            'data' => [
                'id' => $shoe->id,
                'name' => $shoe->name,
                'slug' => $shoe->slug,
                'manufacturer_style_code' => $shoe->manufacturer_style_code,
                'audience' => $shoe->audience->value,
                'description' => $this->localized(
                    $shoe->description_lv,
                    $shoe->description_en,
                    $options['locale'],
                ),
                'brand' => [
                    'name' => $shoe->brand->name,
                    'slug' => $shoe->brand->slug,
                ],
                'category' => [
                    'name' => $this->localized(
                        $shoe->category->name_lv,
                        $shoe->category->name_en,
                        $options['locale'],
                    ),
                    'slug' => $shoe->category->slug,
                    'description' => $this->localized(
                        $shoe->category->description_lv,
                        $shoe->category->description_en,
                        $options['locale'],
                    ),
                ],
                'selected_size' => $selectedSize === null
                    ? null
                    : $this->sizeData($selectedSize),
                'lowest_price' => $this->lowestPriceData($lowestPrice),
                'price_available' => $lowestPrice !== null,
                'variants' => $variants,
            ],
            'meta' => [
                'locale' => $options['locale'],
                'currency' => $options['currency'],
            ],
        ];
    }

    public function filters(array $options): array
    {
        $publicShoe = fn ($query) => $query
            ->where('active', true)
            ->whereHas(
                'brand',
                fn ($query) => $query
                    ->where('active', true),
            )
            ->whereHas(
                'category',
                fn ($query) => $query
                    ->where('active', true),
            );

        $brands = Brand::query()
            ->where('active', true)
            ->whereHas('shoes', $publicShoe)
            ->orderBy('name')
            ->get()
            ->map(fn (Brand $brand): array => [
                'name' => $brand->name,
                'slug' => $brand->slug,
            ])
            ->all();

        $categories = Category::query()
            ->where('active', true)
            ->whereHas('shoes', $publicShoe)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Category $category): array => [
                'name' => $this->localized(
                    $category->name_lv,
                    $category->name_en,
                    $options['locale'],
                ),
                'slug' => $category->slug,
            ])
            ->all();

        $audiences = Shoe::query()
            ->where('active', true)
            ->whereHas(
                'brand',
                fn ($query) => $query
                    ->where('active', true),
            )
            ->whereHas(
                'category',
                fn ($query) => $query
                    ->where('active', true),
            )
            ->distinct()
            ->pluck('audience')
            ->map(function (Audience|string $audience) use ($options): array {
                $audience = is_string($audience)
                    ? Audience::from($audience)
                    : $audience;

                return [
                    'value' => $audience->value,
                    'label' => $this->audienceLabel(
                        $audience,
                        $options['locale'],
                    ),
                ];
            })
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        $colours = FilterColour::query()
            ->where('active', true)
            ->whereHas(
                'colourways',
                fn ($query) => $query
                    ->where('active', true)
                    ->whereHas(
                        'variants',
                        fn ($variantQuery) => $variantQuery
                            ->where('active', true)
                            ->whereHas('shoe', $publicShoe),
                    ),
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (FilterColour $colour): array => [
                'code' => $colour->code,
                'name' => $this->localized(
                    $colour->name_lv,
                    $colour->name_en,
                    $options['locale'],
                ),
            ])
            ->all();

        $qualifyingRows = $this->qualifyingListingSizes->build(
            $options['currency'],
        );

        $sizes = (clone $qualifyingRows)
            ->select([
                'qualified_sizes.id',
                'qualified_sizes.label',
                'qualified_sizes.eu_size',
                'qualified_sizes.sort_order',
            ])
            ->distinct()
            ->orderBy('qualified_sizes.sort_order')
            ->get()
            ->map(fn (object $size): array => [
                'label' => $size->label,
                'eu_size' => $this->normalizeEuSize($size->eu_size),
            ])
            ->all();

        $retailers = (clone $qualifyingRows)
            ->select([
                'qualified_retailers.id',
                'qualified_retailers.name',
                'qualified_retailers.slug',
            ])
            ->distinct()
            ->orderBy('qualified_retailers.name')
            ->get()
            ->map(fn (object $retailer): array => [
                'name' => $retailer->name,
                'slug' => $retailer->slug,
            ])
            ->all();

        $priceBounds = (clone $qualifyingRows)
            ->selectRaw(
                'MIN('.QualifyingListingSizeQuery::EFFECTIVE_PRICE_SQL
                    .') as minimum',
            )
            ->selectRaw(
                'MAX('.QualifyingListingSizeQuery::EFFECTIVE_PRICE_SQL
                    .') as maximum',
            )
            ->first();

        return [
            'data' => [
                'brands' => $brands,
                'categories' => $categories,
                'audiences' => $audiences,
                'colours' => $colours,
                'sizes' => $sizes,
                'retailers' => $retailers,
                'price_bounds' => [
                    'minimum' => $priceBounds?->minimum === null
                        ? null
                        : $this->normalizeAmount($priceBounds->minimum),
                    'maximum' => $priceBounds?->maximum === null
                        ? null
                        : $this->normalizeAmount($priceBounds->maximum),
                    'currency' => $options['currency'],
                ],
            ],
            'meta' => [
                'locale' => $options['locale'],
                'currency' => $options['currency'],
            ],
        ];
    }

    private function publicShoesQuery(): EloquentBuilder
    {
        return Shoe::query()
            ->where('active', true)
            ->whereHas(
                'brand',
                fn ($query) => $query
                    ->where('active', true),
            )
            ->whereHas(
                'category',
                fn ($query) => $query
                    ->where('active', true),
            );
    }

    private function applyShoeFilters(
        EloquentBuilder $query,
        array $filters,
    ): void {
        if (filled($filters['search'] ?? null)) {
            $search = mb_strtolower(trim($filters['search']));

            $query->where(
                fn ($query) => $query
                    ->whereRaw('LOWER(shoes.name) LIKE ?', ["%{$search}%"])
                    ->orWhereHas(
                        'brand',
                        fn ($query) => $query
                            ->whereRaw('LOWER(brands.name) LIKE ?', ["%{$search}%"]),
                    ),
            );
        }

        if (filled($filters['brand'] ?? [])) {
            $query->whereHas(
                'brand',
                fn ($query) => $query
                    ->whereIn('slug', $filters['brand']),
            );
        }

        if (filled($filters['category'] ?? [])) {
            $query->whereHas(
                'category',
                fn ($query) => $query
                    ->whereIn('slug', $filters['category']),
            );
        }

        if (filled($filters['audience'] ?? [])) {
            $query->whereIn('audience', $filters['audience']);
        }

    }

    private function variantMatchesFilters(
        ShoeVariant $variant,
        array $filters,
    ): bool {
        if (
            filled($filters['colour'] ?? [])
            && ! $variant->colour->filterColours
                ->pluck('code')
                ->intersect($filters['colour'])
                ->isNotEmpty()
        ) {
            return false;
        }

        $hasRowFilters = $this->hasRowFilters($filters);

        if (! $hasRowFilters && ! array_key_exists('in_stock', $filters)) {
            return true;
        }

        $baseRows = $this->filteredQualifyingRows($filters)
            ->where('qualified_variants.id', $variant->id);

        if (
            array_key_exists('in_stock', $filters)
            && $filters['in_stock'] === false
        ) {
            return ! $baseRows->exists();
        }

        if (($filters['on_sale'] ?? null) === true) {
            return $this->saleRows(clone $baseRows)->exists();
        }

        if (($filters['on_sale'] ?? null) === false) {
            return (clone $baseRows)->exists()
                && ! $this->saleRows(clone $baseRows)->exists();
        }

        return $baseRows->exists();
    }

    private function filteredQualifyingRows(array $filters): QueryBuilder
    {
        $query = $this->qualifyingListingSizes->build($filters['currency']);

        if (filled($filters['colour'] ?? [])) {
            $query->whereExists(
                fn (QueryBuilder $filterQuery) => $filterQuery
                    ->selectRaw('1')
                    ->from('colour_filter_colour as selected_colour_filters')
                    ->join(
                        'filter_colours as selected_filter_colours',
                        'selected_filter_colours.id',
                        '=',
                        'selected_colour_filters.filter_colour_id',
                    )
                    ->whereColumn(
                        'selected_colour_filters.colour_id',
                        'qualified_colours.id',
                    )
                    ->where('selected_filter_colours.active', true)
                    ->whereIn(
                        'selected_filter_colours.code',
                        $filters['colour'],
                    ),
            );
        }

        if (filled($filters['size'] ?? [])) {
            $query->whereIn('qualified_sizes.label', $filters['size']);
        }

        if (filled($filters['retailer'] ?? [])) {
            $query->whereIn('qualified_retailers.slug', $filters['retailer']);
        }

        if (isset($filters['min_price'])) {
            $query->whereRaw(
                QualifyingListingSizeQuery::EFFECTIVE_PRICE_SQL.' >= ?',
                [$filters['min_price']],
            );
        }

        if (isset($filters['max_price'])) {
            $query->whereRaw(
                QualifyingListingSizeQuery::EFFECTIVE_PRICE_SQL.' <= ?',
                [$filters['max_price']],
            );
        }

        return $query;
    }

    private function saleRows(QueryBuilder $query): QueryBuilder
    {
        return $query
            ->whereNotNull('qualified_listings.original_price')
            ->whereRaw(
                'qualified_listings.original_price > '
                    .QualifyingListingSizeQuery::EFFECTIVE_PRICE_SQL,
            );
    }

    private function hasRowFilters(array $filters): bool
    {
        return filled($filters['size'] ?? [])
            || filled($filters['retailer'] ?? [])
            || isset($filters['min_price'])
            || isset($filters['max_price'])
            || array_key_exists('in_stock', $filters)
            || array_key_exists('on_sale', $filters);
    }

    private function shoeCard(
        Shoe $shoe,
        ShoeVariant $variant,
        string $locale,
        string $currency,
    ): array {
        $rows = $this->qualifyingRowsForVariant($variant->id, $currency);
        $lowest = $rows->first();
        $availableSizes = $rows
            ->unique('size_id')
            ->sortBy('size_sort_order')
            ->values()
            ->map(fn (object $row): array => [
                'label' => $row->size_label,
                'eu_size' => $this->normalizeEuSize($row->eu_size),
            ])
            ->all();
        $onSale = $rows->contains(
            fn (object $row): bool => $row->original_price !== null
                && $this->amountToCents($row->original_price)
                    > $this->amountToCents($row->effective_price),
        );

        return [
            'id' => $shoe->id,
            'variant_id' => $variant->id,
            'card_key' => "{$shoe->id}:{$variant->id}",
            'name' => $shoe->name,
            'slug' => $shoe->slug,
            'brand' => [
                'name' => $shoe->brand->name,
                'slug' => $shoe->brand->slug,
            ],
            'category' => [
                'name' => $this->localized(
                    $shoe->category->name_lv,
                    $shoe->category->name_en,
                    $locale,
                ),
                'slug' => $shoe->category->slug,
            ],
            'audience' => $shoe->audience->value,
            'description' => $this->localized(
                $shoe->description_lv,
                $shoe->description_en,
                $locale,
            ),
            'colour' => $this->colourData($variant->colour, $locale),
            'colours' => $shoe->variants
                ->sort(function (
                    ShoeVariant $left,
                    ShoeVariant $right,
                ): int {
                    $sort = $left->colour->sort_order
                        <=> $right->colour->sort_order;

                    return $sort !== 0
                        ? $sort
                        : ($left->id <=> $right->id);
                })
                ->map(fn (ShoeVariant $item): array => [
                    'variant_id' => $item->id,
                    ...$this->colourData($item->colour, $locale),
                ])
                ->values()
                ->all(),
            'primary_image' => $this->primaryImage($variant, $locale),
            'available_sizes' => $availableSizes,
            'lowest_price' => $lowest === null
                ? null
                : [
                    'amount' => $this->normalizeAmount(
                        $lowest->effective_price,
                    ),
                    'currency' => $currency,
                ],
            'price_available' => $lowest !== null,
            'on_sale' => $onSale,
            '_created_at' => $shoe->created_at->getTimestamp(),
        ];
    }

    private function qualifyingRowsForVariant(
        int $variantId,
        string $currency,
    ): Collection {
        return $this->qualifyingListingSizes
            ->build($currency)
            ->where('qualified_variants.id', $variantId)
            ->select([
                'qualified_listing_sizes.id',
                'qualified_listing_sizes.size_id',
                'qualified_sizes.label as size_label',
                'qualified_sizes.eu_size',
                'qualified_sizes.sort_order as size_sort_order',
                'qualified_listings.original_price',
            ])
            ->selectRaw(
                QualifyingListingSizeQuery::EFFECTIVE_PRICE_SQL
                    .' as effective_price',
            )
            ->orderBy('effective_price')
            ->orderBy('qualified_listings.id')
            ->orderBy('qualified_listing_sizes.id')
            ->get();
    }

    private function variant(
        ShoeVariant $variant,
        ?Size $selectedSize,
        string $locale,
        string $currency,
    ): array {
        $lowestPrice = $this->lowestPriceFinder->forVariant(
            $variant,
            $selectedSize,
            $currency,
        );
        $rows = $this->qualifyingListingSizes
            ->build($currency)
            ->where('qualified_variants.id', $variant->id)
            ->select([
                'qualified_sizes.id',
                'qualified_sizes.label',
                'qualified_sizes.eu_size',
                'qualified_sizes.sort_order',
            ])
            ->distinct()
            ->orderBy('qualified_sizes.sort_order')
            ->get();
        $listings = $variant->retailerListings
            ->map(fn (RetailerListing $listing): array => $this->listing(
                $listing,
                $locale,
            ))
            ->sort(function (array $left, array $right) use ($currency): int {
                $currencyOrder = ($right['currency'] === $currency)
                    <=> ($left['currency'] === $currency);

                if ($currencyOrder !== 0) {
                    return $currencyOrder;
                }

                $freshnessOrder = $right['fresh'] <=> $left['fresh'];

                if ($freshnessOrder !== 0) {
                    return $freshnessOrder;
                }

                $stockOrder = $right['in_stock'] <=> $left['in_stock'];

                if ($stockOrder !== 0) {
                    return $stockOrder;
                }

                $priceOrder = $this->compareNullableAmounts(
                    $left['lowest_price']['amount'] ?? null,
                    $right['lowest_price']['amount'] ?? null,
                );

                if ($priceOrder !== 0) {
                    return $priceOrder;
                }

                return strnatcasecmp(
                    $left['retailer']['name'],
                    $right['retailer']['name'],
                );
            })
            ->values()
            ->all();

        return [
            'id' => $variant->id,
            'manufacturer_variant_code' => $variant->manufacturer_variant_code,
            'colour' => $this->colourData($variant->colour, $locale),
            'images' => $variant->images
                ->map(fn (ShoeImage $image): array => $this->image(
                    $image,
                    $locale,
                ))
                ->all(),
            'available_sizes' => $rows
                ->map(fn (object $size): array => [
                    'label' => $size->label,
                    'eu_size' => $this->normalizeEuSize($size->eu_size),
                ])
                ->all(),
            'lowest_price' => $this->lowestPriceData($lowestPrice),
            'price_available' => $lowestPrice !== null,
            'listings' => $listings,
        ];
    }

    private function listing(
        RetailerListing $listing,
        string $locale,
    ): array {
        $sizes = $listing->listingSizes
            ->sortBy(fn (RetailerListingSize $listingSize): int => $listingSize->size->sort_order)
            ->values()
            ->map(function (RetailerListingSize $listingSize) use ($listing): array {
                $effectivePrice = $listingSize->effectivePrice();

                return [
                    'label' => $listingSize->size->label,
                    'eu_size' => $this->normalizeEuSize(
                        $listingSize->size->eu_size,
                    ),
                    'in_stock' => $listingSize->in_stock,
                    'override_price' => $listingSize->price,
                    'effective_price' => $effectivePrice,
                    'delivered_total' => $this->deliveredTotal(
                        $effectivePrice,
                        $listing->delivery_cost,
                    ),
                ];
            });
        $inStockSizes = $sizes->where('in_stock', true);
        $lowestSize = $inStockSizes
            ->sort(
                fn (array $left, array $right): int => $this->compareAmounts(
                    $left['effective_price'],
                    $right['effective_price'],
                ),
            )
            ->first();
        $lowestAmount = $lowestSize['effective_price'] ?? null;

        return [
            'id' => $listing->id,
            'retailer' => [
                'name' => $listing->retailer->name,
                'slug' => $listing->retailer->slug,
                'logo_url' => $listing->retailer->logo_path === null
                    ? null
                    : Storage::disk('public')->url(
                        $listing->retailer->logo_path,
                    ),
            ],
            'current_price' => $listing->current_price,
            'original_price' => $listing->original_price,
            'lowest_price' => $lowestAmount === null
                ? null
                : [
                    'amount' => $lowestAmount,
                    'currency' => $listing->currency,
                ],
            'currency' => $listing->currency,
            'in_stock' => $inStockSizes->isNotEmpty(),
            'on_sale' => $lowestAmount !== null
                && $listing->original_price !== null
                && $this->amountToCents($listing->original_price)
                    > $this->amountToCents($lowestAmount),
            'delivery' => [
                'cost' => $listing->delivery_cost,
                'min_days' => $listing->delivery_min_days,
                'max_days' => $listing->delivery_max_days,
                'note' => $this->localized(
                    $listing->delivery_note_lv,
                    $listing->delivery_note_en,
                    $locale,
                ),
                'delivered_total' => $lowestAmount === null
                    ? null
                    : $this->deliveredTotal(
                        $lowestAmount,
                        $listing->delivery_cost,
                    ),
            ],
            'fresh' => $this->freshness->isFresh($listing),
            'stale' => $this->freshness->isStale($listing),
            'last_checked_at' => $listing->last_checked_at?->toIso8601String(),
            'sizes' => $sizes->all(),
            'outbound_url' => "/go/{$listing->id}",
        ];
    }

    private function primaryImage(
        ShoeVariant $variant,
        string $locale,
    ): ?array {
        $image = $variant->images
            ->sort(function (ShoeImage $left, ShoeImage $right): int {
                $primary = $right->is_primary <=> $left->is_primary;

                if ($primary !== 0) {
                    return $primary;
                }

                $sort = $left->sort_order <=> $right->sort_order;

                return $sort !== 0 ? $sort : ($left->id <=> $right->id);
            })
            ->first();

        return $image === null ? null : $this->image($image, $locale);
    }

    private function image(ShoeImage $image, string $locale): array
    {
        return [
            'url' => $image->source_type === ImageSourceType::Local
                ? Storage::disk('public')->url($image->path)
                : $image->external_url,
            'alt' => $this->localized(
                $image->alt_text_lv,
                $image->alt_text_en,
                $locale,
            ),
            'primary' => $image->is_primary,
        ];
    }

    private function colourData(Colour $colour, string $locale): array
    {
        return [
            'code' => $colour->code,
            'name' => $colour->name,
            'filter_colours' => $colour->filterColours
                ->map(fn (FilterColour $filterColour): array => [
                    'code' => $filterColour->code,
                    'name' => $this->localized(
                        $filterColour->name_lv,
                        $filterColour->name_en,
                        $locale,
                    ),
                ])
                ->values()
                ->all(),
        ];
    }

    private function sizeData(Size $size): array
    {
        return [
            'label' => $size->label,
            'eu_size' => $this->normalizeEuSize($size->eu_size),
        ];
    }

    private function lowestPriceData(?LowestPrice $price): ?array
    {
        return $price === null
            ? null
            : [
                'amount' => $price->amount,
                'currency' => $price->currency,
            ];
    }

    private function sortCards(array &$cards, string $sort): void
    {
        usort($cards, function (array $left, array $right) use ($sort): int {
            $availabilityOrder = $right['price_available']
                <=> $left['price_available'];

            if ($availabilityOrder !== 0) {
                return $availabilityOrder;
            }

            $comparison = match ($sort) {
                'name' => strnatcasecmp($left['name'], $right['name']),
                'price_asc' => $this->compareNullableAmounts(
                    $left['lowest_price']['amount'] ?? null,
                    $right['lowest_price']['amount'] ?? null,
                ),
                'price_desc' => $this->compareNullableAmounts(
                    $left['lowest_price']['amount'] ?? null,
                    $right['lowest_price']['amount'] ?? null,
                    descending: true,
                ),
                default => $right['_created_at'] <=> $left['_created_at'],
            };

            return $comparison !== 0
                ? $comparison
                : ($left['variant_id'] <=> $right['variant_id']);
        });
    }

    private function compareNullableAmounts(
        ?string $left,
        ?string $right,
        bool $descending = false,
    ): int {
        if ($left === null) {
            return $right === null ? 0 : 1;
        }

        if ($right === null) {
            return -1;
        }

        return $descending
            ? $this->compareAmounts($right, $left)
            : $this->compareAmounts($left, $right);
    }

    private function compareAmounts(string $left, string $right): int
    {
        return $this->amountToCents($left) <=> $this->amountToCents($right);
    }

    private function deliveredTotal(
        string $itemPrice,
        ?string $deliveryCost,
    ): ?string {
        if ($deliveryCost === null) {
            return null;
        }

        return $this->centsToAmount(
            $this->amountToCents($itemPrice)
                + $this->amountToCents($deliveryCost),
        );
    }

    private function amountToCents(mixed $amount): int
    {
        $normalized = $this->normalizeAmount($amount);
        [$whole, $fraction] = explode('.', $normalized);

        return ((int) $whole * 100) + (int) $fraction;
    }

    private function centsToAmount(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad(
            (string) ($cents % 100),
            2,
            '0',
            STR_PAD_LEFT,
        );
    }

    private function normalizeAmount(mixed $amount): string
    {
        $value = is_float($amount)
            ? number_format($amount, 2, '.', '')
            : (string) $amount;

        if (preg_match('/^(\d+)(?:\.(\d+))?$/', $value, $matches) !== 1) {
            throw new InvalidArgumentException(
                'Price must be a nonnegative decimal value.',
            );
        }

        $fraction = str_pad($matches[2] ?? '', 2, '0');

        return $matches[1].'.'.substr($fraction, 0, 2);
    }

    private function normalizeEuSize(mixed $size): string
    {
        return number_format((float) $size, 1, '.', '');
    }

    private function localized(
        ?string $latvian,
        ?string $english,
        string $locale,
    ): ?string {
        return $locale === 'en' ? $english : $latvian;
    }

    private function audienceLabel(Audience $audience, string $locale): string
    {
        return match ($locale) {
            'en' => match ($audience) {
                Audience::Men => 'Men',
                Audience::Women => 'Women',
                Audience::Unisex => 'Unisex',
                Audience::Kids => 'Kids',
            },
            default => match ($audience) {
                Audience::Men => 'Vīriešiem',
                Audience::Women => 'Sievietēm',
                Audience::Unisex => 'Unisex',
                Audience::Kids => 'Bērniem',
            },
        };
    }
}
