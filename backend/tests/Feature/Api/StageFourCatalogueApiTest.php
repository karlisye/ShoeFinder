<?php

namespace Tests\Feature\Api;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Colour;
use App\Models\FilterColour;
use App\Models\Retailer;
use App\Models\RetailerListing;
use App\Models\RetailerListingSize;
use App\Models\Shoe;
use App\Models\ShoeImage;
use App\Models\ShoeVariant;
use App\Models\Size;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageFourCatalogueApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-07-27 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_catalogue_cards_are_localized_and_use_only_qualifying_prices(): void
    {
        $this->createCatalogue();

        $response = $this->getJson('/api/v1/shoes?sort=name');

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.slug', 'alpha-runner')
            ->assertJsonPath('data.0.colour.code', 'red')
            ->assertJsonPath('data.0.colour.name', 'Red')
            ->assertJsonPath('data.0.colour.filter_colours.0.code', 'white')
            ->assertJsonPath('data.0.colour.filter_colours.1.code', 'red')
            ->assertJsonPath('data.0.colours.0.code', 'red')
            ->assertJsonPath('data.0.colours.1.code', 'blue')
            ->assertJsonPath('data.0.category.name', 'Skriešanas apavi')
            ->assertJsonPath('data.0.description', 'Alpha apraksts')
            ->assertJsonPath('data.0.primary_image.alt', 'Alpha sarkanā krāsā')
            ->assertJsonPath('data.0.lowest_price.amount', '90.00')
            ->assertJsonPath('data.0.lowest_price.currency', 'EUR')
            ->assertJsonPath('data.0.price_available', true)
            ->assertJsonPath('data.0.on_sale', true)
            ->assertJsonPath('data.0.available_sizes.0.label', '42')
            ->assertJsonPath('data.0.available_sizes.1.label', '43')
            ->assertJsonPath('data.1.slug', 'alpha-runner')
            ->assertJsonPath('data.1.colour.code', 'blue')
            ->assertJsonPath('data.1.primary_image.alt', 'Alpha zilā krāsā')
            ->assertJsonPath('data.1.lowest_price', null)
            ->assertJsonPath('data.1.price_available', false)
            ->assertJsonPath('data.2.slug', 'beta-walker')
            ->assertJsonPath('meta.currency', 'EUR');

        $this->assertSame(
            $response->json('data.0.id'),
            $response->json('data.1.id'),
        );
        $this->assertNotSame(
            $response->json('data.0.variant_id'),
            $response->json('data.1.variant_id'),
        );
        $this->assertNotSame(
            $response->json('data.0.card_key'),
            $response->json('data.1.card_key'),
        );

        $this->getJson('/api/v1/shoes?locale=en&sort=name')
            ->assertOk()
            ->assertJsonPath('data.0.colour.name', 'Red')
            ->assertJsonPath('data.0.category.name', 'Running shoes')
            ->assertJsonPath('data.0.description', 'Alpha description')
            ->assertJsonPath('data.0.primary_image.alt', 'Alpha in red')
            ->assertJsonPath('data.1.primary_image.alt', 'Alpha in blue');
    }

    public function test_catalogue_filters_match_stable_identifiers_and_one_qualifying_row(): void
    {
        $this->createCatalogue();

        $this->getJson('/api/v1/shoes?brand[]=adidas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'beta-walker');

        $this->getJson('/api/v1/shoes?search=NIKE')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/shoes?size[]=43')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'alpha-runner');

        $this->getJson('/api/v1/shoes?retailer[]=shop-a')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'alpha-runner');

        $this->getJson('/api/v1/shoes?max_price=95')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'alpha-runner');

        $this->getJson('/api/v1/shoes?colour[]=blue')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'alpha-runner')
            ->assertJsonPath('data.0.colour.code', 'blue');

        $this->getJson('/api/v1/shoes?colour[]=white')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.colour.code', 'red')
            ->assertJsonPath('data.1.colour.code', 'red');

        $this->getJson('/api/v1/shoes?colour[]=blue&in_stock=1')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/shoes?in_stock=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'alpha-runner')
            ->assertJsonPath('data.0.colour.code', 'blue');

        $this->getJson('/api/v1/shoes?on_sale=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'beta-walker');

        $this->getJson('/api/v1/shoes?in_stock=true&on_sale=true')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'alpha-runner')
            ->assertJsonPath('data.0.colour.code', 'red');

        $this->getJson('/api/v1/shoes?in_stock=false')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'alpha-runner')
            ->assertJsonPath('data.0.colour.code', 'blue');
    }

    public function test_price_sorting_keeps_unavailable_products_last_and_paginates(): void
    {
        $this->createCatalogue();

        $this->getJson('/api/v1/shoes?sort=price_asc&per_page=2')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'alpha-runner')
            ->assertJsonPath('data.1.slug', 'beta-walker')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 2);

        $this->getJson('/api/v1/shoes?sort=price_desc')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'beta-walker')
            ->assertJsonPath('data.1.slug', 'alpha-runner')
            ->assertJsonPath('data.1.colour.code', 'red')
            ->assertJsonPath('data.2.slug', 'alpha-runner')
            ->assertJsonPath('data.2.colour.code', 'blue');

        $this->getJson('/api/v1/shoes?sort=price_asc&per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'alpha-runner')
            ->assertJsonPath('data.0.colour.code', 'blue')
            ->assertJsonPath('meta.from', 3)
            ->assertJsonPath('meta.to', 3);
    }

    public function test_shoe_detail_supports_size_selection_and_keeps_stale_listings_visible(): void
    {
        $catalogue = $this->createCatalogue();

        $response = $this->getJson(
            '/api/v1/shoes/alpha-runner?locale=en&size=43',
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Alpha Runner')
            ->assertJsonPath('data.description', 'Alpha description')
            ->assertJsonPath('data.category.name', 'Running shoes')
            ->assertJsonPath('data.selected_size.label', '43')
            ->assertJsonPath('data.lowest_price.amount', '100.00')
            ->assertJsonPath('data.variants.0.colour.name', 'Red')
            ->assertJsonPath('data.variants.0.lowest_price.amount', '100.00')
            ->assertJsonPath('data.variants.0.listings.0.retailer.slug', 'shop-a')
            ->assertJsonPath('data.variants.0.listings.0.fresh', true)
            ->assertJsonPath('data.variants.0.listings.0.delivery.delivered_total', '95.00')
            ->assertJsonPath('data.variants.0.listings.0.sizes.0.effective_price', '90.00')
            ->assertJsonPath('data.variants.0.listings.0.sizes.0.delivered_total', '95.00')
            ->assertJsonPath('data.variants.1.listings.0.retailer.slug', 'shop-b')
            ->assertJsonPath('data.variants.1.listings.0.fresh', false)
            ->assertJsonPath('data.variants.1.listings.0.stale', true)
            ->assertJsonPath(
                'data.variants.1.listings.0.outbound_url',
                "/go/{$catalogue['staleListing']->id}",
            )
            ->assertJsonMissingPath('data.variants.0.listings.0.raw_payload')
            ->assertJsonMissingPath('data.variants.0.listings.0.product_url')
            ->assertJsonMissingPath('data.variants.0.listings.0.affiliate_url')
            ->assertJsonMissingPath('data.variants.0.listings.0.retailer_sku');

        $this->getJson('/api/v1/shoes/inactive-shoe')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'shoe_not_found');
    }

    public function test_filter_options_are_localized_and_price_bounds_are_fresh(): void
    {
        $this->createCatalogue();

        $response = $this->getJson(
            '/api/v1/catalog-filters?locale=en&currency=EUR',
        );

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data.brands')
            ->assertJsonCount(2, 'data.categories')
            ->assertJsonCount(2, 'data.audiences')
            ->assertJsonCount(3, 'data.colours')
            ->assertJsonCount(3, 'data.sizes')
            ->assertJsonCount(2, 'data.retailers')
            ->assertJsonPath('data.categories.0.name', 'Running shoes')
            ->assertJsonPath('data.colours.0.name', 'White')
            ->assertJsonPath('data.sizes.0.label', '42')
            ->assertJsonPath('data.retailers.0.slug', 'shop-a')
            ->assertJsonPath('data.price_bounds.minimum', '90.00')
            ->assertJsonPath('data.price_bounds.maximum', '150.00')
            ->assertJsonPath('data.price_bounds.currency', 'EUR')
            ->assertJsonPath('meta.locale', 'en');
    }

    public function test_invalid_queries_and_unsupported_routes_use_stable_errors(): void
    {
        $this->createCatalogue();

        $this->getJson('/api/v1/shoes?currency=eur')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure([
                'error' => ['code', 'message', 'details' => ['currency']],
            ]);

        $this->getJson('/api/v1/shoes?brand[]=unknown')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure([
                'error' => ['details' => ['brand.0']],
            ]);

        $this->getJson('/api/v1/shoes?min_price=100&max_price=90')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');

        $this->postJson('/api/v1/shoes')
            ->assertMethodNotAllowed()
            ->assertJsonPath('error.code', 'method_not_allowed');

        $this->getJson('/api/v1/unknown')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    }

    private function createCatalogue(): array
    {
        $nike = Brand::create([
            'name' => 'Nike',
            'slug' => 'nike',
            'active' => true,
        ]);
        $adidas = Brand::create([
            'name' => 'Adidas',
            'slug' => 'adidas',
            'active' => true,
        ]);
        $inactiveBrand = Brand::create([
            'name' => 'Hidden brand',
            'slug' => 'hidden-brand',
            'active' => false,
        ]);
        $running = Category::create([
            'slug' => 'running',
            'name_lv' => 'Skriešanas apavi',
            'name_en' => 'Running shoes',
            'sort_order' => 1,
            'active' => true,
        ]);
        $lifestyle = Category::create([
            'slug' => 'lifestyle',
            'name_lv' => 'Ikdienas apavi',
            'name_en' => 'Lifestyle shoes',
            'sort_order' => 2,
            'active' => true,
        ]);
        $red = Colour::create([
            'code' => 'red',
            'name' => 'Red',
            'sort_order' => 1,
            'active' => true,
        ]);
        $blue = Colour::create([
            'code' => 'blue',
            'name' => 'Blue',
            'sort_order' => 2,
            'active' => true,
        ]);
        $red->filterColours()->attach([
            FilterColour::query()->where('code', 'red')->value('id'),
            FilterColour::query()->where('code', 'white')->value('id'),
        ]);
        $blue->filterColours()->attach(
            FilterColour::query()->where('code', 'blue')->value('id'),
        );
        $size42 = $this->createSize('42', 42, 1);
        $size43 = $this->createSize('43', 43, 2);
        $size44 = $this->createSize('44', 44, 3);
        $shopA = $this->createRetailer('Shop A', 'shop-a');
        $shopB = $this->createRetailer('Shop B', 'shop-b');
        $shopC = $this->createRetailer('Shop C', 'shop-c');

        $alpha = Shoe::create([
            'brand_id' => $nike->id,
            'category_id' => $running->id,
            'name' => 'Alpha Runner',
            'slug' => 'alpha-runner',
            'audience' => 'men',
            'description_lv' => 'Alpha apraksts',
            'description_en' => 'Alpha description',
            'active' => true,
        ]);
        $alphaRed = $this->createVariant($alpha, $red, 'ALPHA-RED');
        $alphaBlue = $this->createVariant($alpha, $blue, 'ALPHA-BLUE');
        ShoeImage::create([
            'shoe_variant_id' => $alphaRed->id,
            'source_type' => 'external',
            'external_url' => 'https://images.example.com/alpha-red.jpg',
            'alt_text_lv' => 'Alpha sarkanā krāsā',
            'alt_text_en' => 'Alpha in red',
            'sort_order' => 0,
            'is_primary' => true,
        ]);
        ShoeImage::create([
            'shoe_variant_id' => $alphaBlue->id,
            'source_type' => 'external',
            'external_url' => 'https://images.example.com/alpha-blue.jpg',
            'alt_text_lv' => 'Alpha zilā krāsā',
            'alt_text_en' => 'Alpha in blue',
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        $freshListing = $this->createListing(
            $alphaRed,
            $shopA,
            [
                'current_price' => '100.00',
                'original_price' => '120.00',
                'delivery_cost' => '5.00',
                'last_checked_at' => now()->subHour(),
            ],
        );
        $this->createListingSize($freshListing, $size42, '90.00');
        $this->createListingSize($freshListing, $size43, null);

        $staleListing = $this->createListing(
            $alphaBlue,
            $shopB,
            [
                'current_price' => '80.00',
                'original_price' => null,
                'delivery_cost' => null,
                'last_checked_at' => now()->subDays(8),
            ],
        );
        $this->createListingSize($staleListing, $size42, null);

        $beta = Shoe::create([
            'brand_id' => $adidas->id,
            'category_id' => $lifestyle->id,
            'name' => 'Beta Walker',
            'slug' => 'beta-walker',
            'audience' => 'women',
            'description_lv' => 'Beta apraksts',
            'description_en' => 'Beta description',
            'active' => true,
        ]);
        $betaRed = $this->createVariant($beta, $red, 'BETA-RED');
        $betaListing = $this->createListing(
            $betaRed,
            $shopC,
            [
                'current_price' => '150.00',
                'original_price' => null,
                'last_checked_at' => now()->subHour(),
            ],
        );
        $this->createListingSize($betaListing, $size44, null);

        $gamma = Shoe::create([
            'brand_id' => $nike->id,
            'category_id' => $running->id,
            'name' => 'Gamma Trainer',
            'slug' => 'gamma-trainer',
            'audience' => 'men',
            'active' => true,
        ]);

        Shoe::create([
            'brand_id' => $inactiveBrand->id,
            'category_id' => $running->id,
            'name' => 'Inactive Shoe',
            'slug' => 'inactive-shoe',
            'audience' => 'unisex',
            'active' => true,
        ]);

        return compact(
            'alpha',
            'beta',
            'gamma',
            'freshListing',
            'staleListing',
        );
    }

    private function createSize(
        string $label,
        float $euSize,
        int $sortOrder,
    ): Size {
        return Size::create([
            'label' => $label,
            'eu_size' => $euSize,
            'sort_order' => $sortOrder,
            'active' => true,
        ]);
    }

    private function createRetailer(string $name, string $slug): Retailer
    {
        return Retailer::create([
            'name' => $name,
            'slug' => $slug,
            'active' => true,
        ]);
    }

    private function createVariant(
        Shoe $shoe,
        Colour $colour,
        string $code,
    ): ShoeVariant {
        return ShoeVariant::create([
            'shoe_id' => $shoe->id,
            'colour_id' => $colour->id,
            'manufacturer_variant_code' => $code,
            'active' => true,
        ]);
    }

    private function createListing(
        ShoeVariant $variant,
        Retailer $retailer,
        array $overrides,
    ): RetailerListing {
        return RetailerListing::create([
            'shoe_variant_id' => $variant->id,
            'retailer_id' => $retailer->id,
            'product_url' => "https://{$retailer->slug}.example/product",
            'affiliate_url' => "https://{$retailer->slug}.example/affiliate",
            'retailer_external_id' => "external-{$variant->id}",
            'retailer_sku' => "sku-{$variant->id}",
            'gtin' => '1234567890123',
            'raw_title' => 'Private retailer title',
            'raw_colour' => 'Private colour',
            'source_type' => 'manual',
            'raw_payload' => ['private' => true],
            'current_price' => '99.00',
            'original_price' => null,
            'currency' => 'EUR',
            'delivery_cost' => null,
            'delivery_min_days' => 1,
            'delivery_max_days' => 3,
            'delivery_note_lv' => 'Piegādes piezīme',
            'delivery_note_en' => 'Delivery note',
            'active' => true,
            'last_checked_at' => now(),
            ...$overrides,
        ]);
    }

    private function createListingSize(
        RetailerListing $listing,
        Size $size,
        ?string $price,
    ): RetailerListingSize {
        return RetailerListingSize::create([
            'retailer_listing_id' => $listing->id,
            'size_id' => $size->id,
            'in_stock' => true,
            'price' => $price,
        ]);
    }
}
