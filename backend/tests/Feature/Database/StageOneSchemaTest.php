<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StageOneSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_stage_one_tables_and_identity_columns_exist(): void
    {
        $tables = [
            'brands',
            'categories',
            'colours',
            'filter_colours',
            'colour_filter_colour',
            'sizes',
            'retailers',
            'shoes',
            'shoe_variants',
            'shoe_images',
            'retailer_listings',
            'retailer_listing_sizes',
            'price_changes',
            'outbound_clicks',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $this->assertTrue(Schema::hasColumns('colours', [
            'code',
            'name',
            'sort_order',
            'active',
        ]));
        $this->assertFalse(Schema::hasColumn('colours', 'name_lv'));
        $this->assertFalse(Schema::hasColumn('colours', 'name_en'));
        $this->assertTrue(Schema::hasColumns('filter_colours', [
            'code',
            'name_lv',
            'name_en',
            'sort_order',
            'active',
        ]));
        $this->assertTrue(Schema::hasColumns('colour_filter_colour', [
            'colour_id',
            'filter_colour_id',
        ]));

        $this->assertTrue(Schema::hasColumns('retailer_listings', [
            'retailer_external_id',
            'retailer_sku',
            'gtin',
            'manufacturer_style_code',
            'raw_title',
            'raw_colour',
            'source_type',
            'raw_payload',
            'current_price',
            'original_price',
            'currency',
            'delivery_cost',
            'delivery_min_days',
            'delivery_max_days',
            'delivery_note_lv',
            'delivery_note_en',
            'active',
            'last_checked_at',
        ]));
    }

    public function test_one_listing_can_store_multiple_size_rows(): void
    {
        $records = $this->createCatalogueGraph();
        $secondSizeId = DB::table('sizes')->insertGetId([
            'eu_size' => 42.5,
            'label' => '42.5',
            'sort_order' => 1,
        ]);

        DB::table('retailer_listing_sizes')->insert([
            [
                'retailer_listing_id' => $records['listing_id'],
                'size_id' => $records['size_id'],
                'in_stock' => true,
                'price' => null,
            ],
            [
                'retailer_listing_id' => $records['listing_id'],
                'size_id' => $secondSizeId,
                'in_stock' => true,
                'price' => 94.99,
            ],
        ]);

        $this->assertDatabaseCount('retailer_listings', 1);
        $this->assertDatabaseCount('retailer_listing_sizes', 2);
    }

    public function test_listing_and_size_pair_must_be_unique(): void
    {
        $records = $this->createCatalogueGraph();
        $row = [
            'retailer_listing_id' => $records['listing_id'],
            'size_id' => $records['size_id'],
            'in_stock' => true,
            'price' => null,
        ];

        DB::table('retailer_listing_sizes')->insert($row);

        $this->expectException(QueryException::class);

        DB::table('retailer_listing_sizes')->insert($row);
    }

    public function test_retailer_can_have_only_one_listing_for_a_variant(): void
    {
        $records = $this->createCatalogueGraph();

        $this->expectException(QueryException::class);

        DB::table('retailer_listings')->insert([
            ...$this->baseListing($records),
            'product_url' => 'https://shop.example/duplicate',
            'retailer_external_id' => 'external-duplicate',
            'retailer_sku' => 'sku-duplicate',
        ]);
    }

    #[DataProvider('duplicateRetailerIdentityData')]
    public function test_retailer_import_identities_are_unique_within_a_retailer(
        array $overrides,
    ): void {
        $records = $this->createCatalogueGraph();
        $records['variant_id'] = $this->createSecondVariant($records);

        $this->expectException(QueryException::class);

        DB::table('retailer_listings')->insert([
            ...$this->baseListing($records),
            'product_url' => 'https://shop.example/second-variant',
            ...$overrides,
        ]);
    }

    public static function duplicateRetailerIdentityData(): array
    {
        return [
            'external id' => [
                [
                    'retailer_external_id' => 'external-001',
                    'retailer_sku' => 'sku-002',
                ],
            ],
            'sku' => [
                [
                    'retailer_external_id' => 'external-002',
                    'retailer_sku' => 'sku-001',
                ],
            ],
        ];
    }

    public function test_import_identities_can_repeat_across_retailers(): void
    {
        $records = $this->createCatalogueGraph();
        $secondRetailerId = DB::table('retailers')->insertGetId([
            'name' => 'Second Shop',
            'slug' => 'second-shop',
        ]);

        DB::table('retailer_listings')->insert([
            ...$this->baseListing($records),
            'retailer_id' => $secondRetailerId,
            'product_url' => 'https://second-shop.example/example-runner',
        ]);

        $this->assertDatabaseCount('retailer_listings', 2);
    }

    public function test_price_history_is_removed_with_listing(): void
    {
        $records = $this->createCatalogueGraph();

        $priceChangeId = DB::table('price_changes')->insertGetId([
            'retailer_listing_id' => $records['listing_id'],
            'price' => 99.99,
            'original_price' => 119.99,
        ]);

        DB::table('retailer_listings')
            ->where('id', $records['listing_id'])
            ->delete();

        $this->assertDatabaseMissing('price_changes', ['id' => $priceChangeId]);
    }

    #[DataProvider('invalidListingData')]
    public function test_postgresql_listing_checks_reject_invalid_data(array $overrides): void
    {
        $this->requirePostgreSql();
        $records = $this->createCatalogueGraphWithoutListing();

        $this->expectException(QueryException::class);

        DB::table('retailer_listings')->insert([
            ...$this->baseListing($records),
            ...$overrides,
        ]);
    }

    public static function invalidListingData(): array
    {
        return [
            'negative current price' => [['current_price' => -0.01]],
            'original below current price' => [['original_price' => 50.00]],
            'lowercase currency' => [['currency' => 'eur']],
            'negative delivery cost' => [['delivery_cost' => -1.00]],
            'inverted delivery range' => [
                [
                    'delivery_min_days' => 5,
                    'delivery_max_days' => 2,
                ],
            ],
            'invalid gtin' => [['gtin' => '12345']],
            'invalid product url' => [['product_url' => '/relative-product']],
        ];
    }

    #[DataProvider('invalidImageData')]
    public function test_postgresql_image_source_check_rejects_invalid_data(array $image): void
    {
        $this->requirePostgreSql();
        $records = $this->createCatalogueGraphWithoutListing();

        $this->expectException(QueryException::class);

        DB::table('shoe_images')->insert([
            'shoe_variant_id' => $records['variant_id'],
            'sort_order' => 0,
            'is_primary' => true,
            ...$image,
        ]);
    }

    public static function invalidImageData(): array
    {
        return [
            'local without path' => [
                [
                    'source_type' => 'local',
                    'path' => null,
                    'external_url' => null,
                ],
            ],
            'external with non-https url' => [
                [
                    'source_type' => 'external',
                    'path' => null,
                    'external_url' => 'http://images.example/shoe.jpg',
                ],
            ],
        ];
    }

    public function test_postgresql_size_price_check_rejects_negative_data(): void
    {
        $this->requirePostgreSql();
        $records = $this->createCatalogueGraph();

        $this->expectException(QueryException::class);

        DB::table('retailer_listing_sizes')->insert([
            'retailer_listing_id' => $records['listing_id'],
            'size_id' => $records['size_id'],
            'in_stock' => true,
            'price' => -1.00,
        ]);
    }

    public function test_postgresql_price_history_check_rejects_invalid_data(): void
    {
        $this->requirePostgreSql();
        $records = $this->createCatalogueGraph();

        $this->expectException(QueryException::class);

        DB::table('price_changes')->insert([
            'retailer_listing_id' => $records['listing_id'],
            'price' => 100.00,
            'original_price' => 50.00,
        ]);
    }

    public function test_postgresql_click_check_rejects_external_referrer(): void
    {
        $this->requirePostgreSql();
        $records = $this->createCatalogueGraph();

        $this->expectException(QueryException::class);

        DB::table('outbound_clicks')->insert([
            'retailer_listing_id' => $records['listing_id'],
            'locale' => 'lv',
            'referrer_path' => 'https://external.example/product',
        ]);
    }

    private function createCatalogueGraph(): array
    {
        $records = $this->createCatalogueGraphWithoutListing();
        $records['listing_id'] = DB::table('retailer_listings')
            ->insertGetId($this->baseListing($records));

        return $records;
    }

    private function createCatalogueGraphWithoutListing(): array
    {
        $brandId = DB::table('brands')->insertGetId([
            'name' => 'Example Brand',
            'slug' => 'example-brand',
        ]);
        $categoryId = DB::table('categories')->insertGetId([
            'slug' => 'trainers',
            'name_lv' => 'Sporta apavi',
            'name_en' => 'Trainers',
        ]);
        $colourId = DB::table('colours')->insertGetId([
            'code' => 'black',
            'name' => 'Black',
        ]);
        $sizeId = DB::table('sizes')->insertGetId([
            'eu_size' => 42.0,
            'label' => '42',
            'sort_order' => 0,
        ]);
        $retailerId = DB::table('retailers')->insertGetId([
            'name' => 'Example Shop',
            'slug' => 'example-shop',
        ]);
        $shoeId = DB::table('shoes')->insertGetId([
            'brand_id' => $brandId,
            'category_id' => $categoryId,
            'name' => 'Example Runner',
            'slug' => 'example-runner',
            'manufacturer_style_code' => 'STYLE-001',
            'audience' => 'unisex',
        ]);
        $variantId = DB::table('shoe_variants')->insertGetId([
            'shoe_id' => $shoeId,
            'colour_id' => $colourId,
            'manufacturer_variant_code' => 'STYLE-001-BLK',
        ]);

        return [
            'brand_id' => $brandId,
            'category_id' => $categoryId,
            'colour_id' => $colourId,
            'size_id' => $sizeId,
            'retailer_id' => $retailerId,
            'shoe_id' => $shoeId,
            'variant_id' => $variantId,
        ];
    }

    private function createSecondVariant(array $records): int
    {
        $colourId = DB::table('colours')->insertGetId([
            'code' => 'blue',
            'name' => 'Blue',
        ]);

        return DB::table('shoe_variants')->insertGetId([
            'shoe_id' => $records['shoe_id'],
            'colour_id' => $colourId,
            'manufacturer_variant_code' => 'STYLE-001-BLU',
        ]);
    }

    private function baseListing(array $records): array
    {
        return [
            'shoe_variant_id' => $records['variant_id'],
            'retailer_id' => $records['retailer_id'],
            'product_url' => 'https://shop.example/example-runner',
            'affiliate_url' => 'https://shop.example/affiliate/example-runner',
            'retailer_external_id' => 'external-001',
            'retailer_sku' => 'sku-001',
            'gtin' => '1234567890123',
            'manufacturer_style_code' => 'STYLE-001',
            'raw_title' => 'Example Runner Black',
            'raw_colour' => 'Black',
            'source_type' => 'manual',
            'raw_payload' => null,
            'current_price' => 99.99,
            'original_price' => 119.99,
            'currency' => 'EUR',
            'delivery_cost' => 3.99,
            'delivery_min_days' => 2,
            'delivery_max_days' => 4,
            'active' => true,
        ];
    }

    private function requirePostgreSql(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL-specific constraint.');
        }
    }
}
