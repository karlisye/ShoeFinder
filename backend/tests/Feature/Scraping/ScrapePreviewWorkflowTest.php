<?php

namespace Tests\Feature\Scraping;

use App\Domain\Scraping\ScrapeRunQueue;
use App\Domain\Scraping\ScrapeRunWorkflow;
use App\Jobs\PreviewScrapeRun;
use App\Models\ScrapeRun;
use App\Models\ScrapeRunItem;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CreatesCatalogueData;
use Tests\TestCase;

class ScrapePreviewWorkflowTest extends TestCase
{
    use CreatesCatalogueData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'scraper.crawl_delay_ms' => 0,
            'scraper.queue' => 'scrapes',
        ]);
    }

    public function test_start_persists_snapshots_and_dispatches_to_the_scrapes_queue(): void
    {
        Queue::fake();
        $context = $this->ballzyContext('scrape-queue');
        $listing = $this->createListing($context['variant'], $context['retailer'], [
            'product_url' => 'https://ballzy.eu/en/product/test-shoe-style-100-9-cnf',
        ]);
        $this->createListingSize($listing, $context['size']);
        $user = User::factory()->create();

        $run = app(ScrapeRunQueue::class)->start($context['retailer'], $user);

        $this->assertSame(ScrapeRun::STATUS_QUEUED, $run->status);
        $this->assertSame(1, $run->total_count);
        $this->assertSame('99.99', $run->items->first()->baseline['current_price']);
        Queue::assertPushedOn('scrapes', PreviewScrapeRun::class);
    }

    public function test_preview_stores_changes_without_writing_to_the_catalogue(): void
    {
        Queue::fake();
        $payload = $this->successPayload();
        $context = $this->ballzyContext('scrape-preview');
        $listing = $this->createListing($context['variant'], $context['retailer'], [
            'product_url' => $payload['requested_url'],
        ]);
        $this->createListingSize($listing, $context['size']);
        $historyCount = $listing->priceChanges()->count();
        $run = app(ScrapeRunQueue::class)->start($context['retailer']);
        $payload['request_id'] = (string) $run->items()->value('id');
        Process::fake([
            '*' => Process::result(output: json_encode($payload)),
        ])->preventStrayProcesses();
        $aggregateSql = null;
        DB::listen(function (QueryExecuted $query) use (&$aggregateSql): void {
            if (str_contains($query->sql, 'count(*) as aggregate')) {
                $aggregateSql = $query->sql;
            }
        });

        app(ScrapeRunWorkflow::class)->preview($run);

        $item = $run->items()->firstOrFail();
        $this->assertSame(ScrapeRun::STATUS_READY, $run->refresh()->status);
        $this->assertSame(1, $run->successful_count);
        $this->assertSame(ScrapeRunItem::STATUS_CHANGED, $item->status);
        $this->assertSame('89.99', $item->result_payload['current_price']);
        $this->assertSame('99.99', $listing->refresh()->current_price);
        $this->assertSame($historyCount, $listing->priceChanges()->count());
        $this->assertNotNull($aggregateSql);
        $this->assertStringNotContainsString('order by', strtolower($aggregateSql));
    }

    public function test_identical_sizes_remain_unchanged_when_json_object_keys_are_reordered(): void
    {
        Queue::fake();
        $payload = $this->successPayload();
        $context = $this->ballzyContext('scrape-unchanged-sizes');
        $listing = $this->createListing($context['variant'], $context['retailer'], [
            'product_url' => $payload['requested_url'],
            'current_price' => $payload['current_price'],
            'original_price' => $payload['original_price'],
        ]);
        $this->createListingSize($listing, $context['size']);
        $run = app(ScrapeRunQueue::class)->start($context['retailer']);
        $payload['request_id'] = (string) $run->items()->value('id');
        Process::fake([
            '*' => Process::result(output: json_encode($payload)),
        ])->preventStrayProcesses();

        app(ScrapeRunWorkflow::class)->preview($run);

        $item = $run->items()->firstOrFail();
        $this->assertSame(ScrapeRun::STATUS_READY, $run->refresh()->status);
        $this->assertSame(0, $run->changed_count);
        $this->assertSame(ScrapeRunItem::STATUS_UNCHANGED, $item->status);
        $this->assertNull($item->changes);
    }

    public function test_invalid_scraper_results_fail_safely(): void
    {
        Queue::fake();
        $payload = $this->successPayload();
        $payload['sizes'][0]['eu_size'] = '99';
        $context = $this->ballzyContext('scrape-invalid');
        $listing = $this->createListing($context['variant'], $context['retailer'], [
            'product_url' => $payload['requested_url'],
        ]);
        $run = app(ScrapeRunQueue::class)->start($context['retailer']);
        $payload['request_id'] = (string) $run->items()->value('id');
        Process::fake(['*' => Process::result(output: json_encode($payload))]);

        app(ScrapeRunWorkflow::class)->preview($run);

        $item = $run->items()->firstOrFail();
        $this->assertSame(ScrapeRun::STATUS_FAILED, $run->refresh()->status);
        $this->assertSame(ScrapeRunItem::STATUS_FAILED, $item->status);
        $this->assertSame('unknown_size', $item->error['code']);
        $this->assertSame('99.99', $listing->refresh()->current_price);
    }

    public function test_only_manual_listings_for_supported_retailers_are_eligible(): void
    {
        Queue::fake();
        $context = $this->ballzyContext('scrape-scope');
        $this->createListing($context['variant'], $context['retailer'], [
            'product_url' => 'https://ballzy.eu/en/product/manual-shoe',
        ]);
        $otherVariant = $this->createVariant($context['shoe'], 'scrape-scope-feed');
        $this->createListing($otherVariant, $context['retailer'], [
            'product_url' => 'https://ballzy.eu/en/product/feed-shoe',
            'source_type' => 'feed',
        ]);

        $run = app(ScrapeRunQueue::class)->start();

        $this->assertSame(1, $run->total_count);
        $this->assertSame('https://ballzy.eu/en/product/manual-shoe', $run->items->first()->product_url);
    }

    private function ballzyContext(string $suffix): array
    {
        $context = $this->createCatalogueContext($suffix);
        $context['retailer']->update([
            'name' => 'Ballzy',
            'slug' => 'ballzy',
            'website_url' => 'https://ballzy.eu',
        ]);
        $context['variant']->update(['manufacturer_variant_code' => 'STYLE-100']);

        return $context;
    }

    private function successPayload(): array
    {
        return [
            'schema_version' => 1,
            'ok' => true,
            'request_id' => '1',
            'requested_url' => 'https://ballzy.eu/en/product/test-shoe-style-100-9-cnf',
            'final_url' => 'https://ballzy.eu/en/product/test-shoe-style-100-9-cnf',
            'observed_at' => '2026-08-02T12:00:00Z',
            'availability' => 'available',
            'current_price' => '89.99',
            'original_price' => '119.99',
            'currency' => 'EUR',
            'sku' => 'STYLE_100_9_CNF',
            'sizes' => [
                ['eu_size' => '42', 'in_stock' => true],
            ],
        ];
    }
}
