<?php

namespace Tests\Feature\Database;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\CreatesCatalogueData;
use Tests\TestCase;

class StageTwoPriceHistoryTest extends TestCase
{
    use CreatesCatalogueData;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_listing_history_records_initial_and_changed_prices_only(): void
    {
        $initialTime = CarbonImmutable::parse(
            '2026-07-27 12:00:00 UTC',
        );
        CarbonImmutable::setTestNow($initialTime);
        $context = $this->createCatalogueContext();
        $listing = $this->createListing(
            $context['variant'],
            $context['retailer'],
            [
                'current_price' => '99.99',
                'original_price' => '119.99',
            ],
        );

        $this->assertDatabaseCount('price_changes', 1);
        $initial = $listing->priceChanges()->sole();
        $this->assertSame('99.99', $initial->price);
        $this->assertSame('119.99', $initial->original_price);
        $this->assertTrue($initial->observed_at->equalTo($initialTime));

        $listing->update(['delivery_cost' => '4.99']);
        $listing->update(['current_price' => '99.99']);
        $this->assertDatabaseCount('price_changes', 1);

        $changedTime = $initialTime->addHour();
        CarbonImmutable::setTestNow($changedTime);
        $listing->update([
            'current_price' => '89.99',
            'original_price' => '109.99',
        ]);

        $this->assertDatabaseCount('price_changes', 2);
        $changed = $listing->priceChanges()->latest('id')->firstOrFail();
        $this->assertSame('89.99', $changed->price);
        $this->assertSame('109.99', $changed->original_price);
        $this->assertTrue($changed->observed_at->equalTo($changedTime));

        CarbonImmutable::setTestNow($changedTime->addHour());
        $listing->update(['original_price' => '104.99']);

        $this->assertDatabaseCount('price_changes', 3);
        $originalPriceChange = $listing->priceChanges()->latest('id')->firstOrFail();
        $this->assertSame('89.99', $originalPriceChange->price);
        $this->assertSame('104.99', $originalPriceChange->original_price);

        $listingSize = $this->createListingSize(
            $listing,
            $context['size'],
            ['price' => '85.00'],
        );
        $listingSize->update(['price' => '80.00']);

        $this->assertDatabaseCount('price_changes', 3);
    }

    public function test_listing_and_initial_history_roll_back_together(): void
    {
        $context = $this->createCatalogueContext();

        try {
            DB::transaction(function () use ($context): void {
                $this->createListing(
                    $context['variant'],
                    $context['retailer'],
                );

                throw new RuntimeException('Rollback test transaction.');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Rollback test transaction.',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseCount('retailer_listings', 0);
        $this->assertDatabaseCount('price_changes', 0);
    }
}
