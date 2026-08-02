<?php

namespace Tests\Feature\Scraping;

use App\Domain\Scraping\ScrapeRunQueue;
use App\Domain\Scraping\ScrapeRunWorkflow;
use App\Jobs\ApplyScrapeRun;
use App\Models\ScrapeRun;
use App\Models\ScrapeRunItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CreatesCatalogueData;
use Tests\TestCase;

class ScrapeApplyWorkflowTest extends TestCase
{
    use CreatesCatalogueData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config(['scraper.crawl_delay_ms' => 0, 'scraper.queue' => 'scrapes']);
    }

    public function test_approval_is_queued_and_applies_price_stock_and_freshness(): void
    {
        $context = $this->ballzyContext('scrape-apply');
        $size43 = $this->createSize('43', 43, 2);
        $listing = $this->createListing($context['variant'], $context['retailer'], [
            'product_url' => $this->productUrl(),
        ]);
        $this->createListingSize($listing, $context['size']);
        $historyCount = $listing->priceChanges()->count();
        $run = $this->preview($context, [
            ['eu_size' => '42', 'in_stock' => false],
            ['eu_size' => '43', 'in_stock' => true],
        ]);

        $queuedRun = app(ScrapeRunQueue::class)->apply($run);

        $this->assertSame(ScrapeRun::STATUS_APPLY_QUEUED, $queuedRun->status);
        Queue::assertPushedOn('scrapes', ApplyScrapeRun::class);

        app(ScrapeRunWorkflow::class)->apply($queuedRun);

        $listing->refresh();
        $this->assertSame(ScrapeRun::STATUS_APPLIED, $run->refresh()->status);
        $this->assertSame('89.99', $listing->current_price);
        $this->assertSame('119.99', $listing->original_price);
        $this->assertTrue($listing->active);
        $this->assertSame('2026-08-02T12:00:00+00:00', $listing->last_checked_at->toIso8601String());
        $this->assertSame($historyCount + 1, $listing->priceChanges()->count());
        $this->assertFalse($listing->listingSizes()->where('size_id', $context['size']->id)->firstOrFail()->in_stock);
        $this->assertTrue($listing->listingSizes()->where('size_id', $size43->id)->firstOrFail()->in_stock);
        $this->assertSame(ScrapeRunItem::STATUS_APPLIED, $run->items()->firstOrFail()->status);

        (new ApplyScrapeRun($run->id))->handle(app(ScrapeRunWorkflow::class));

        $this->assertSame($historyCount + 1, $listing->priceChanges()->count());
    }

    public function test_unavailable_results_deactivate_the_listing_and_clear_stock(): void
    {
        $context = $this->ballzyContext('scrape-unavailable');
        $listing = $this->createListing($context['variant'], $context['retailer'], [
            'product_url' => $this->productUrl(),
        ]);
        $this->createListingSize($listing, $context['size']);
        $run = $this->preview($context, [], 'unavailable');

        $this->assertSame(ScrapeRun::STATUS_READY, $run->status, json_encode([
            'run' => $run->toArray(),
            'items' => $run->items()->get()->toArray(),
        ]));

        app(ScrapeRunWorkflow::class)->apply(app(ScrapeRunQueue::class)->apply($run));

        $listing->refresh();
        $this->assertFalse($listing->active);
        $this->assertSame('99.99', $listing->current_price);
        $this->assertFalse($listing->listingSizes()->firstOrFail()->in_stock);
    }

    public function test_catalogue_changes_after_preview_make_the_whole_run_stale(): void
    {
        $context = $this->ballzyContext('scrape-stale');
        $listing = $this->createListing($context['variant'], $context['retailer'], [
            'product_url' => $this->productUrl(),
        ]);
        $this->createListingSize($listing, $context['size']);
        $run = $this->preview($context, [['eu_size' => '42', 'in_stock' => true]]);
        $listing->update(['current_price' => '95.00', 'original_price' => '119.99']);

        app(ScrapeRunWorkflow::class)->apply(app(ScrapeRunQueue::class)->apply($run));

        $this->assertSame(ScrapeRun::STATUS_STALE, $run->refresh()->status);
        $this->assertSame('baseline_changed', $run->errors[0]['code']);
        $this->assertSame('95.00', $listing->refresh()->current_price);
        $this->assertSame(ScrapeRunItem::STATUS_CHANGED, $run->items()->firstOrFail()->status);
    }

    private function preview(array $context, array $sizes, string $availability = 'available'): ScrapeRun
    {
        $payload = [
            'schema_version' => 1,
            'ok' => true,
            'request_id' => '1',
            'requested_url' => $this->productUrl(),
            'final_url' => $this->productUrl(),
            'observed_at' => '2026-08-02T12:00:00Z',
            'availability' => $availability,
            'current_price' => $availability === 'available' ? '89.99' : null,
            'original_price' => $availability === 'available' ? '119.99' : null,
            'currency' => $availability === 'available' ? 'EUR' : null,
            'sku' => 'STYLE_100_9_CNF',
            'sizes' => $sizes,
        ];
        $run = app(ScrapeRunQueue::class)->start($context['retailer']);
        $payload['request_id'] = (string) $run->items()->value('id');
        Process::fake(['*' => Process::result(output: json_encode($payload))]);

        return app(ScrapeRunWorkflow::class)->preview($run);
    }

    private function ballzyContext(string $suffix): array
    {
        $context = $this->createCatalogueContext($suffix);
        $context['retailer']->update(['name' => 'Ballzy', 'slug' => 'ballzy']);
        $context['variant']->update(['manufacturer_variant_code' => 'STYLE-100']);

        return $context;
    }

    private function productUrl(): string
    {
        return 'https://ballzy.eu/en/product/test-shoe-style-100-9-cnf';
    }
}
