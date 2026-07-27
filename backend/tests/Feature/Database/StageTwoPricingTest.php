<?php

namespace Tests\Feature\Database;

use App\Domain\Catalogue\Pricing\ListingFreshness;
use App\Domain\Catalogue\Pricing\LowestPriceFinder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\CreatesCatalogueData;
use Tests\TestCase;

class StageTwoPricingTest extends TestCase
{
    use CreatesCatalogueData;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_freshness_uses_an_inclusive_cutoff_and_treats_missing_checks_as_stale(): void
    {
        config(['catalogue.offer_stale_after_hours' => 168]);
        $now = CarbonImmutable::parse('2026-07-27 12:00:00 UTC');
        $context = $this->createCatalogueContext();
        $listing = $this->createListing(
            $context['variant'],
            $context['retailer'],
            ['last_checked_at' => $now->subHours(168)],
        );
        $freshness = app(ListingFreshness::class);

        $this->assertTrue($freshness->isFresh($listing, $now));

        $listing->last_checked_at = $now->subHours(168)->subSecond();
        $this->assertTrue($freshness->isStale($listing, $now));

        $listing->last_checked_at = null;
        $this->assertTrue($freshness->isStale($listing, $now));
    }

    public function test_listing_size_price_overrides_or_inherits_the_listing_price(): void
    {
        $context = $this->createCatalogueContext();
        $listing = $this->createListing(
            $context['variant'],
            $context['retailer'],
            ['current_price' => '100.00'],
        );
        $inherited = $this->createListingSize(
            $listing,
            $context['size'],
        );
        $secondSize = $this->createSize('43', 43, 2);
        $overridden = $this->createListingSize(
            $listing,
            $secondSize,
            ['price' => '84.50'],
        );

        $this->assertSame('100.00', $inherited->effectivePrice());
        $this->assertSame('84.50', $overridden->effectivePrice());
    }

    public function test_lowest_price_uses_only_fresh_active_in_stock_rows_for_the_requested_currency(): void
    {
        $now = CarbonImmutable::parse('2026-07-27 12:00:00 UTC');
        CarbonImmutable::setTestNow($now);
        $context = $this->createCatalogueContext();
        $secondSize = $this->createSize('43', 43, 2);

        $firstListing = $this->createListing(
            $context['variant'],
            $context['retailer'],
            ['current_price' => '100.00', 'last_checked_at' => $now],
        );
        $this->createListingSize(
            $firstListing,
            $context['size'],
        );
        $firstListingSecondSize = $this->createListingSize(
            $firstListing,
            $secondSize,
            ['price' => '70.00'],
        );

        $secondRetailer = $this->createRetailer('second');
        $secondListing = $this->createListing(
            $context['variant'],
            $secondRetailer,
            ['current_price' => '80.00', 'last_checked_at' => $now],
        );
        $secondListingSize = $this->createListingSize(
            $secondListing,
            $context['size'],
            ['price' => '75.00'],
        );

        $staleRetailer = $this->createRetailer('stale');
        $stale = $this->createListing(
            $context['variant'],
            $staleRetailer,
            [
                'current_price' => '10.00',
                'last_checked_at' => $now->subHours(169),
            ],
        );
        $this->createListingSize($stale, $context['size']);

        $inactiveRetailer = $this->createRetailer('inactive');
        $inactive = $this->createListing(
            $context['variant'],
            $inactiveRetailer,
            [
                'current_price' => '5.00',
                'active' => false,
                'last_checked_at' => $now,
            ],
        );
        $this->createListingSize($inactive, $context['size']);

        $outOfStockRetailer = $this->createRetailer('out-of-stock');
        $outOfStock = $this->createListing(
            $context['variant'],
            $outOfStockRetailer,
            ['current_price' => '1.00', 'last_checked_at' => $now],
        );
        $this->createListingSize(
            $outOfStock,
            $context['size'],
            ['in_stock' => false],
        );

        $usdRetailer = $this->createRetailer('usd');
        $usd = $this->createListing(
            $context['variant'],
            $usdRetailer,
            [
                'current_price' => '2.00',
                'currency' => 'USD',
                'last_checked_at' => $now,
            ],
        );
        $this->createListingSize($usd, $context['size']);

        $finder = app(LowestPriceFinder::class);
        $beforeSizeSelection = $finder->forShoe(
            $context['shoe'],
            at: $now,
        );
        $afterSizeSelection = $finder->forShoe(
            $context['shoe'],
            size: $context['size'],
            at: $now,
        );
        $variantPrice = $finder->forVariant(
            $context['variant'],
            size: $context['size'],
            at: $now,
        );

        $this->assertNotNull($beforeSizeSelection);
        $this->assertSame('70.00', $beforeSizeSelection->amount);
        $this->assertSame('EUR', $beforeSizeSelection->currency);
        $this->assertSame(
            $firstListingSecondSize->id,
            $beforeSizeSelection->listingSizeId,
        );
        $this->assertNotNull($afterSizeSelection);
        $this->assertSame('75.00', $afterSizeSelection->amount);
        $this->assertSame(
            $secondListingSize->id,
            $afterSizeSelection->listingSizeId,
        );
        $this->assertNotNull($variantPrice);
        $this->assertSame('75.00', $variantPrice->amount);
    }

    public function test_inactive_parent_records_remove_a_price_from_qualification(): void
    {
        $now = CarbonImmutable::parse('2026-07-27 12:00:00 UTC');
        $context = $this->createCatalogueContext();
        $listing = $this->createListing(
            $context['variant'],
            $context['retailer'],
            ['last_checked_at' => $now],
        );
        $this->createListingSize($listing, $context['size']);
        $finder = app(LowestPriceFinder::class);

        $this->assertNotNull($finder->forShoe($context['shoe'], at: $now));

        $context['shoe']->update(['active' => false]);
        $this->assertNull($finder->forShoe($context['shoe'], at: $now));

        $context['shoe']->update(['active' => true]);
        $context['variant']->update(['active' => false]);
        $this->assertNull($finder->forShoe($context['shoe'], at: $now));

        $context['variant']->update(['active' => true]);
        $context['retailer']->update(['active' => false]);
        $this->assertNull($finder->forShoe($context['shoe'], at: $now));
    }

    public function test_lowest_price_rejects_an_invalid_currency_code(): void
    {
        $context = $this->createCatalogueContext();

        $this->expectException(InvalidArgumentException::class);

        app(LowestPriceFinder::class)->forShoe(
            $context['shoe'],
            currency: 'eur',
        );
    }
}
