<?php

namespace Tests\Feature\Database;

use App\Models\ScrapeRun;
use App\Models\ScrapeRunItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCatalogueData;
use Tests\TestCase;

class ScrapeRunPersistenceTest extends TestCase
{
    use CreatesCatalogueData;
    use RefreshDatabase;

    public function test_scrape_runs_store_durable_listing_snapshots(): void
    {
        $context = $this->createCatalogueContext('scrape-persistence');
        $listing = $this->createListing($context['variant'], $context['retailer']);
        $user = User::factory()->create();
        $run = ScrapeRun::create([
            'user_id' => $user->id,
            'retailer_id' => $context['retailer']->id,
            'status' => ScrapeRun::STATUS_READY,
            'total_count' => 1,
            'successful_count' => 1,
            'changed_count' => 1,
        ]);
        $item = $run->items()->create([
            'retailer_listing_id' => $listing->id,
            'position' => 1,
            'status' => ScrapeRunItem::STATUS_CHANGED,
            'product_url' => $listing->product_url,
            'listing_label' => 'Retailer / Shoe / Colour',
            'baseline' => [
                'current_price' => '99.99',
                'sizes' => [['eu_size' => '42', 'in_stock' => true]],
            ],
            'result_payload' => [
                'current_price' => '89.99',
                'sizes' => [['eu_size' => '42', 'in_stock' => true]],
            ],
            'changes' => ['current_price' => ['before' => '99.99', 'after' => '89.99']],
            'observed_at' => '2026-08-02 12:00:00+00:00',
        ]);

        $this->assertTrue($run->fresh()->canApply());
        $this->assertSame($user->id, $run->user->id);
        $this->assertSame($context['retailer']->id, $run->retailer->id);
        $this->assertSame($listing->id, $item->retailerListing->id);
        $this->assertSame('89.99', $item->fresh()->result_payload['current_price']);
        $this->assertTrue($item->wasSuccessful());
    }

    public function test_deleting_a_listing_preserves_the_scrape_audit_record(): void
    {
        $context = $this->createCatalogueContext('scrape-deleted-listing');
        $listing = $this->createListing($context['variant'], $context['retailer']);
        $run = ScrapeRun::create(['status' => ScrapeRun::STATUS_READY]);
        $item = $run->items()->create([
            'retailer_listing_id' => $listing->id,
            'position' => 1,
            'status' => ScrapeRunItem::STATUS_UNCHANGED,
            'product_url' => $listing->product_url,
            'listing_label' => 'Deleted listing',
            'baseline' => ['current_price' => '99.99'],
        ]);

        $listing->delete();

        $this->assertNull($item->fresh()->retailer_listing_id);
        $this->assertSame('Deleted listing', $item->listing_label);
    }
}
