<?php

namespace Tests\Feature\Feeds;

use App\Domain\Feeds\Data\FeedRecord;
use App\Domain\Feeds\FeedProductMatcher;
use App\Enums\ListingSourceType;
use App\Models\FilterColour;
use Database\Seeders\SizeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCatalogueData;
use Tests\TestCase;

class FeedImportCommandTest extends TestCase
{
    use CreatesCatalogueData;
    use RefreshDatabase;

    public function test_dry_run_previews_without_writing(): void
    {
        $context = $this->feedContext();

        $this->artisan('feeds:import', [
            'retailer' => 'sole-market',
            'path' => 'clean/sole-market.csv',
        ])
            ->expectsOutputToContain('DRY RUN: sole-market')
            ->expectsOutputToContain('created')
            ->assertSuccessful();

        $this->assertSame(0, $context['retailer']->listings()->count());
    }

    public function test_apply_creates_and_updates_a_strongly_matched_listing(): void
    {
        $context = $this->feedContext();

        $this->artisan('feeds:import', [
            'retailer' => 'sole-market',
            'path' => 'clean/sole-market.csv',
            '--apply' => true,
        ])
            ->expectsOutputToContain('APPLIED: sole-market')
            ->assertSuccessful();

        $listing = $context['retailer']->listings()->firstOrFail();

        $this->assertSame($context['variant']->id, $listing->shoe_variant_id);
        $this->assertSame(ListingSourceType::Feed, $listing->source_type);
        $this->assertSame('101.99', $listing->current_price);
        $this->assertSame('SOLEMARKET-00001', $listing->retailer_external_id);
        $this->assertSame(5, $listing->listingSizes()->count());
        $this->assertSame(1, $listing->priceChanges()->count());
        $this->assertSame('CW2288-111', $listing->raw_payload['variant_code']);

        $this->artisan('feeds:import', [
            'retailer' => 'sole-market',
            'path' => 'clean/sole-market.csv',
            '--apply' => true,
        ])
            ->expectsOutputToContain('unchanged')
            ->assertSuccessful();

        $this->assertSame(1, $listing->priceChanges()->count());

        $this->artisan('feeds:import', [
            'retailer' => 'sole-market',
            'path' => 'updates/sole-market.csv',
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame('91.99', $listing->refresh()->current_price);
        $this->assertSame(2, $listing->priceChanges()->count());
    }

    public function test_invalid_apply_is_rejected_without_partial_writes(): void
    {
        $context = $this->feedContext();

        $this->artisan('feeds:import', [
            'retailer' => 'sole-market',
            'path' => 'invalid/sole-market-invalid.csv',
            '--apply' => true,
        ])
            ->expectsOutputToContain('No changes were applied.')
            ->assertFailed();

        $this->assertSame(0, $context['retailer']->listings()->count());
    }

    public function test_missing_feed_listing_is_reported_without_deactivation(): void
    {
        $context = $this->feedContext();
        $secondVariant = $this->createVariant($context['shoe'], 'feed-missing');
        $missing = $this->createListing(
            $secondVariant,
            $context['retailer'],
            [
                'retailer_external_id' => 'MISSING-FROM-SNAPSHOT',
                'retailer_sku' => 'MISSING-SKU',
                'source_type' => ListingSourceType::Feed->value,
            ],
        );

        $this->artisan('feeds:import', [
            'retailer' => 'sole-market',
            'path' => 'clean/sole-market.csv',
        ])
            ->expectsOutputToContain('missing')
            ->assertSuccessful();

        $this->assertTrue($missing->refresh()->active);
    }

    public function test_conflicting_strong_identity_requires_manual_review(): void
    {
        $context = $this->feedContext();
        $listing = $this->createListing(
            $context['variant'],
            $context['retailer'],
            [
                'retailer_external_id' => 'SOLEMARKET-00001',
                'retailer_sku' => 'sol-CW2288-111',
                'gtin' => '4750000000015',
                'manufacturer_style_code' => 'CW2288',
            ],
        );
        $data = $this->initialRecords()['sole-market'][0];
        $data['gtin'] = '4750000000022';
        $record = new FeedRecord(2, $data, $data);

        $match = app(FeedProductMatcher::class)->match(
            $context['retailer'],
            $record,
        );

        $this->assertSame('manual_review', $match->action);
        $this->assertSame('strong_identity_conflict', $match->reason);
        $this->assertTrue($listing->is($match->listing));
    }

    public function test_filter_colours_never_match_a_different_official_colourway(): void
    {
        $context = $this->feedContext();
        $context['variant']->update([
            'manufacturer_variant_code' => null,
        ]);
        $context['colour']->filterColours()->sync(
            FilterColour::query()
                ->whereIn('code', ['black', 'white'])
                ->pluck('id'),
        );
        $record = new FeedRecord(2, [
            'brand' => 'Nike',
            'manufacturer_style_code' => 'CW2288',
            'colour' => 'White/Black',
        ], []);

        $match = app(FeedProductMatcher::class)->match(
            $context['retailer'],
            $record,
        );

        $this->assertSame('manual_review', $match->action);
        $this->assertSame('no_strong_match', $match->reason);
    }

    private function feedContext(): array
    {
        $context = $this->createCatalogueContext('feed-import');
        $context['size']->update(['sort_order' => 1000]);
        $this->seed(SizeSeeder::class);

        $context['brand']->update(['name' => 'Nike']);
        $context['shoe']->update([
            'name' => 'Air Force 1 \'07',
            'manufacturer_style_code' => 'CW2288',
        ]);
        $context['colour']->update([
            'name' => 'White/White',
        ]);
        $context['variant']->update([
            'manufacturer_variant_code' => 'CW2288-111',
        ]);
        $context['retailer']->update([
            'name' => 'Sole Market',
            'slug' => 'sole-market',
        ]);

        return $context;
    }

    private function initialRecords(): array
    {
        return json_decode(
            file_get_contents(
                base_path('tests/Fixtures/ProductFeeds/expected/initial-normalized.json'),
            ),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
