<?php

namespace Tests\Feature;

use App\Models\OutboundClick;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCatalogueData;
use Tests\TestCase;

class StageSixOutboundRedirectTest extends TestCase
{
    use CreatesCatalogueData;
    use RefreshDatabase;

    public function test_affiliate_redirect_records_a_privacy_limited_click(): void
    {
        $context = $this->createCatalogueContext('affiliate');
        $listing = $this->createListing(
            $context['variant'],
            $context['retailer'],
        );

        $response = $this->get(
            "/go/{$listing->id}?locale=en&referrer=%2Fen%2Fshoes%2Fshoe-affiliate%3Fsize%3D42",
        );

        $response
            ->assertRedirect(
                "https://{$context['retailer']->slug}.example/go/shoe",
            )
            ->assertStatus(302);

        $click = OutboundClick::query()->sole();

        $this->assertSame($listing->id, $click->retailer_listing_id);
        $this->assertSame('en', $click->locale->value);
        $this->assertSame(
            '/en/shoes/shoe-affiliate?size=42',
            $click->referrer_path,
        );
        $this->assertNotNull($click->clicked_at);
    }

    public function test_product_url_is_used_when_affiliate_url_is_missing(): void
    {
        $context = $this->createCatalogueContext('product');
        $listing = $this->createListing(
            $context['variant'],
            $context['retailer'],
            ['affiliate_url' => null],
        );

        $this->get("/go/{$listing->id}")
            ->assertRedirect(
                "https://{$context['retailer']->slug}.example/shoe",
            )
            ->assertStatus(302);

        $this->assertDatabaseHas('outbound_clicks', [
            'retailer_listing_id' => $listing->id,
            'locale' => 'lv',
            'referrer_path' => null,
        ]);
    }

    public function test_external_referrer_is_discarded(): void
    {
        $context = $this->createCatalogueContext('referrer');
        $listing = $this->createListing(
            $context['variant'],
            $context['retailer'],
        );

        $this->get(
            "/go/{$listing->id}?locale=invalid&referrer=https%3A%2F%2Fevil.example",
        )->assertRedirect();

        $this->assertDatabaseHas('outbound_clicks', [
            'retailer_listing_id' => $listing->id,
            'locale' => 'lv',
            'referrer_path' => null,
        ]);
    }

    public function test_non_public_listings_do_not_redirect_or_record_clicks(): void
    {
        $context = $this->createCatalogueContext('inactive');
        $listing = $this->createListing(
            $context['variant'],
            $context['retailer'],
            ['active' => false],
        );

        $this->get("/go/{$listing->id}")->assertNotFound();
        $this->assertDatabaseCount('outbound_clicks', 0);

        $listing->update(['active' => true]);
        $context['retailer']->update(['active' => false]);

        $this->get("/go/{$listing->id}")->assertNotFound();
        $this->assertDatabaseCount('outbound_clicks', 0);
    }

    public function test_missing_listing_returns_not_found(): void
    {
        $this->get('/go/999999')->assertNotFound();
        $this->assertDatabaseCount('outbound_clicks', 0);
    }
}
