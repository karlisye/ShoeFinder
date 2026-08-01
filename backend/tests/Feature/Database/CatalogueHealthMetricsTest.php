<?php

namespace Tests\Feature\Database;

use App\Domain\Analytics\CatalogueHealthMetrics;
use App\Models\Shoe;
use App\Models\ShoeImage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCatalogueData;
use Tests\TestCase;

class CatalogueHealthMetricsTest extends TestCase
{
    use CreatesCatalogueData;
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-08-01 12:00:00 UTC');
        CarbonImmutable::setTestNow($this->now);
        config(['catalogue.offer_stale_after_hours' => 168]);

        $this->createCatalogueHealthData();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_metrics_report_catalogue_coverage_and_issues(): void
    {
        $metrics = app(CatalogueHealthMetrics::class);

        $this->assertSame([
            'public_shoes' => 2,
            'public_variants' => 2,
            'qualifying_listings' => 1,
            'qualifying_retailers' => 1,
            'stale_listings' => 1,
            'fresh_listings_without_stock' => 1,
            'variants_without_primary_image' => 1,
            'shoes_without_qualifying_listing' => 1,
            'stale_after_hours' => 168,
        ], $metrics->summary($this->now));

        $variants = $metrics
            ->variantsNeedingAttentionQuery($this->now)
            ->get()
            ->keyBy('manufacturer_variant_code');

        $this->assertCount(2, $variants);
        $this->assertTrue((bool) $variants['VARIANT-health-live']->has_primary_image);
        $this->assertSame(1, $variants['VARIANT-health-live']->qualifying_listings_count);
        $this->assertSame(1, $variants['VARIANT-health-live']->stale_listings_count);
        $this->assertSame(1, $variants['VARIANT-health-live']->fresh_listings_without_stock_count);
        $this->assertFalse((bool) $variants['VARIANT-health-unavailable']->has_primary_image);
        $this->assertSame(0, $variants['VARIANT-health-unavailable']->qualifying_listings_count);
        $this->assertSame(0, $variants['VARIANT-health-unavailable']->stale_listings_count);
        $this->assertSame(0, $variants['VARIANT-health-unavailable']->fresh_listings_without_stock_count);
    }

    private function createCatalogueHealthData(): void
    {
        $healthy = $this->createCatalogueContext('health-live');
        ShoeImage::create([
            'shoe_variant_id' => $healthy['variant']->id,
            'source_type' => 'external',
            'external_url' => 'https://images.example.com/health-live.jpg',
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        $liveListing = $this->createListing(
            $healthy['variant'],
            $healthy['retailer'],
            ['last_checked_at' => $this->now->subHour()],
        );
        $this->createListingSize(
            $liveListing,
            $healthy['size'],
        );

        $staleListing = $this->createListing(
            $healthy['variant'],
            $this->createRetailer('health-stale'),
            ['last_checked_at' => $this->now->subDays(8)],
        );
        $this->createListingSize(
            $staleListing,
            $healthy['size'],
        );

        $noStockListing = $this->createListing(
            $healthy['variant'],
            $this->createRetailer('health-no-stock'),
            ['last_checked_at' => $this->now->subHours(2)],
        );
        $this->createListingSize(
            $noStockListing,
            $healthy['size'],
            ['in_stock' => false],
        );

        $unavailableShoe = Shoe::create([
            'brand_id' => $healthy['brand']->id,
            'category_id' => $healthy['category']->id,
            'name' => 'Shoe health unavailable',
            'slug' => 'shoe-health-unavailable',
            'manufacturer_style_code' => 'STYLE-health-unavailable',
            'audience' => 'unisex',
        ]);
        $this->createVariant($unavailableShoe, 'health-unavailable');

        $inactiveShoe = Shoe::create([
            'brand_id' => $healthy['brand']->id,
            'category_id' => $healthy['category']->id,
            'name' => 'Shoe health inactive',
            'slug' => 'shoe-health-inactive',
            'manufacturer_style_code' => 'STYLE-health-inactive',
            'audience' => 'unisex',
            'active' => false,
        ]);
        $inactiveVariant = $this->createVariant(
            $inactiveShoe,
            'health-inactive',
        );
        $inactiveListing = $this->createListing(
            $inactiveVariant,
            $this->createRetailer('health-inactive'),
            ['last_checked_at' => null],
        );
        $this->createListingSize(
            $inactiveListing,
            $healthy['size'],
        );
    }
}
