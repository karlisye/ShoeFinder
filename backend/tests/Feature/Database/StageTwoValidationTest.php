<?php

namespace Tests\Feature\Database;

use App\Domain\Catalogue\Validation\CatalogueRules;
use App\Models\ShoeImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesCatalogueData;
use Tests\TestCase;

class StageTwoValidationTest extends TestCase
{
    use CreatesCatalogueData;
    use RefreshDatabase;

    public function test_valid_listing_data_passes_reusable_validation(): void
    {
        $context = $this->createCatalogueContext();
        $data = $this->listingPayload(
            $context['variant'],
            $context['retailer'],
        );

        $validator = Validator::make(
            $data,
            app(CatalogueRules::class)->listing($data),
        );

        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    #[DataProvider('invalidListingData')]
    public function test_invalid_listing_data_is_rejected(
        array $overrides,
        string $field,
    ): void {
        $context = $this->createCatalogueContext();
        $data = $this->listingPayload(
            $context['variant'],
            $context['retailer'],
            $overrides,
        );

        $validator = Validator::make(
            $data,
            app(CatalogueRules::class)->listing($data),
        );

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has($field));
    }

    public static function invalidListingData(): array
    {
        return [
            'original price below current price' => [
                ['original_price' => '50.00'],
                'original_price',
            ],
            'invalid GTIN' => [
                ['gtin' => '12345'],
                'gtin',
            ],
            'lowercase currency' => [
                ['currency' => 'eur'],
                'currency',
            ],
            'negative delivery cost' => [
                ['delivery_cost' => '-0.01'],
                'delivery_cost',
            ],
            'inverted delivery range' => [
                [
                    'delivery_min_days' => 5,
                    'delivery_max_days' => 2,
                ],
                'delivery_max_days',
            ],
        ];
    }

    public function test_image_source_requires_exactly_one_matching_location(): void
    {
        $context = $this->createCatalogueContext();
        $rules = app(CatalogueRules::class);
        $invalidLocal = [
            'shoe_variant_id' => $context['variant']->id,
            'source_type' => 'local',
            'external_url' => 'https://images.example/shoe.webp',
        ];
        $invalidExternal = [
            'shoe_variant_id' => $context['variant']->id,
            'source_type' => 'external',
            'external_url' => 'http://images.example/shoe.webp',
        ];
        $validLocal = [
            'shoe_variant_id' => $context['variant']->id,
            'source_type' => 'local',
            'path' => 'shoes/example.webp',
            'sort_order' => 0,
        ];

        $this->assertTrue(
            Validator::make(
                $invalidLocal,
                $rules->image($invalidLocal),
            )->fails(),
        );
        $this->assertTrue(
            Validator::make(
                $invalidExternal,
                $rules->image($invalidExternal),
            )->fails(),
        );
        $this->assertTrue(
            Validator::make(
                $validLocal,
                $rules->image($validLocal),
            )->passes(),
        );
    }

    public function test_delivery_range_allows_either_bound_to_be_unknown(): void
    {
        $context = $this->createCatalogueContext();
        $rules = app(CatalogueRules::class);
        $maximumOnly = $this->listingPayload(
            $context['variant'],
            $context['retailer'],
            [
                'delivery_min_days' => null,
                'delivery_max_days' => 4,
            ],
        );
        $minimumOnly = [
            ...$maximumOnly,
            'delivery_min_days' => 2,
            'delivery_max_days' => null,
        ];

        $this->assertTrue(
            Validator::make(
                $maximumOnly,
                $rules->listing($maximumOnly),
            )->passes(),
        );
        $this->assertTrue(
            Validator::make(
                $minimumOnly,
                $rules->listing($minimumOnly),
            )->passes(),
        );
    }

    public function test_small_integer_inputs_fit_the_postgresql_range(): void
    {
        $context = $this->createCatalogueContext();
        $rules = app(CatalogueRules::class);
        $categoryData = [
            'slug' => 'oversized-sort-order',
            'name_lv' => 'Kategorija',
            'name_en' => 'Category',
            'sort_order' => 32768,
            'active' => true,
        ];
        $listingData = $this->listingPayload(
            $context['variant'],
            $context['retailer'],
            [
                'delivery_min_days' => 32768,
                'delivery_max_days' => null,
            ],
        );

        $this->assertTrue(
            Validator::make(
                $categoryData,
                $rules->category(),
            )->errors()->has('sort_order'),
        );
        $this->assertTrue(
            Validator::make(
                $listingData,
                $rules->listing($listingData),
            )->errors()->has('delivery_min_days'),
        );
    }

    public function test_scoped_listing_identities_are_unique_and_updates_ignore_the_record(): void
    {
        $context = $this->createCatalogueContext();
        $listing = $this->createListing(
            $context['variant'],
            $context['retailer'],
        );
        $secondVariant = $this->createVariant(
            $context['shoe'],
            'second',
        );
        $duplicate = $this->listingPayload(
            $secondVariant,
            $context['retailer'],
            [
                'retailer_external_id' => $listing->retailer_external_id,
                'retailer_sku' => 'different-sku',
            ],
        );
        $existing = $listing->toArray();

        $duplicateValidator = Validator::make(
            $duplicate,
            app(CatalogueRules::class)->listing($duplicate),
        );
        $updateValidator = Validator::make(
            $existing,
            app(CatalogueRules::class)->listing($existing, $listing),
        );

        $this->assertTrue(
            $duplicateValidator->errors()->has('retailer_external_id'),
        );
        $this->assertTrue(
            $updateValidator->passes(),
            $updateValidator->errors()->toJson(),
        );
    }

    public function test_listing_size_pair_and_image_order_are_unique_within_their_parent(): void
    {
        $context = $this->createCatalogueContext();
        $listing = $this->createListing(
            $context['variant'],
            $context['retailer'],
        );
        $listingSize = $this->createListingSize(
            $listing,
            $context['size'],
        );
        $image = ShoeImage::create([
            'shoe_variant_id' => $context['variant']->id,
            'source_type' => 'local',
            'path' => 'shoes/example.webp',
            'sort_order' => 0,
        ]);
        $listingSizeData = [
            'retailer_listing_id' => $listing->id,
            'size_id' => $context['size']->id,
            'in_stock' => true,
            'price' => null,
        ];
        $imageData = [
            'shoe_variant_id' => $context['variant']->id,
            'source_type' => 'local',
            'path' => 'shoes/second.webp',
            'sort_order' => 0,
        ];
        $rules = app(CatalogueRules::class);

        $this->assertTrue(
            Validator::make(
                $listingSizeData,
                $rules->listingSize($listingSizeData),
            )->errors()->has('size_id'),
        );
        $this->assertTrue(
            Validator::make(
                $listingSizeData,
                $rules->listingSize($listingSizeData, $listingSize),
            )->passes(),
        );
        $this->assertTrue(
            Validator::make(
                $imageData,
                $rules->image($imageData),
            )->errors()->has('sort_order'),
        );
        $this->assertTrue(
            Validator::make(
                $image->getAttributes(),
                $rules->image($image->getAttributes(), $image),
            )->passes(),
        );
    }
}
