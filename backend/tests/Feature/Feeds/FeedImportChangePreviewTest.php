<?php

namespace Tests\Feature\Feeds;

use App\Domain\Feeds\FeedImportChangePreview;
use App\Models\FeedImport;
use App\Models\FeedImportItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCatalogueData;
use Tests\TestCase;

class FeedImportChangePreviewTest extends TestCase
{
    use CreatesCatalogueData;
    use RefreshDatabase;

    public function test_preview_compares_listing_fields_and_complete_size_state(): void
    {
        $context = $this->createCatalogueContext('feed-change-preview');
        $listing = $this->createListing(
            $context['variant'],
            $context['retailer'],
        );
        $this->createListingSize($listing, $context['size']);
        $newSize = $this->createSize('43', 43, 2);
        $removedSize = $this->createSize('44', 44, 3);
        $this->createListingSize($listing, $removedSize);
        $feedImport = $this->createFeedImport($context);
        $item = $feedImport->items()->create([
            'source_record' => 2,
            'identity' => 'CHANGE-PREVIEW-1',
            'outcome' => 'updated',
            'reason' => 'retailer_identity',
            'matched_listing_id' => $listing->id,
            'matched_variant_id' => $context['variant']->id,
            'normalized_payload' => [
                'retailer_external_id' => $listing->retailer_external_id,
                'retailer_sku' => $listing->retailer_sku,
                'gtin' => $listing->gtin,
                'manufacturer_style_code' => $listing->manufacturer_style_code,
                'manufacturer_variant_code' => $context['variant']->manufacturer_variant_code,
                'title' => $listing->raw_title,
                'colour' => $listing->raw_colour,
                'product_url' => $listing->product_url,
                'affiliate_url' => $listing->affiliate_url,
                'current_price' => '89.99',
                'original_price' => $listing->original_price,
                'currency' => 'EUR',
                'delivery' => [
                    'cost' => $listing->delivery_cost,
                    'min_days' => $listing->delivery_min_days,
                    'max_days' => $listing->delivery_max_days,
                    'note_lv' => $listing->delivery_note_lv,
                    'note_en' => $listing->delivery_note_en,
                ],
                'sizes' => [
                    [
                        'eu_size' => $context['size']->label,
                        'in_stock' => false,
                        'price' => null,
                    ],
                    [
                        'eu_size' => $newSize->label,
                        'in_stock' => true,
                        'price' => '84.99',
                    ],
                ],
                'active' => true,
                'observed_at' => '2026-07-30T09:00:00+03:00',
            ],
            'raw_payload' => ['source' => 'preview-test'],
        ]);

        $preview = app(FeedImportChangePreview::class)->build($item);
        $fields = collect($preview['fields'])->keyBy('label');
        $sizes = collect($preview['sizes'])->keyBy('label');

        $this->assertTrue($preview['will_apply']);
        $this->assertSame(
            '99.99 EUR',
            $fields['Current price']['current'],
        );
        $this->assertSame(
            '89.99 EUR',
            $fields['Current price']['incoming'],
        );
        $this->assertSame('manual', $fields['Source type']['current']);
        $this->assertSame('feed', $fields['Source type']['incoming']);
        $this->assertSame(
            'In stock, listing price',
            $sizes['EU 42']['current'],
        );
        $this->assertSame(
            'Out of stock, listing price',
            $sizes['EU 42']['incoming'],
        );
        $this->assertSame('Not listed', $sizes['EU 43']['current']);
        $this->assertSame(
            'In stock, 84.99 EUR',
            $sizes['EU 43']['incoming'],
        );
        $this->assertSame(
            'In stock, listing price',
            $sizes['EU 44']['current'],
        );
        $this->assertSame('Not listed', $sizes['EU 44']['incoming']);
        $this->assertSame('99.99', $listing->fresh()->current_price);
        $this->assertSame(2, $listing->listingSizes()->count());
        $this->assertTrue($listing->listingSizes()->firstOrFail()->in_stock);
    }

    public function test_preview_shows_values_for_a_new_listing(): void
    {
        $context = $this->createCatalogueContext('feed-change-new');
        $feedImport = $this->createFeedImport($context);
        $item = $feedImport->items()->create([
            'source_record' => 2,
            'identity' => 'CHANGE-PREVIEW-NEW',
            'outcome' => 'created',
            'reason' => 'strong_variant_identity',
            'matched_variant_id' => $context['variant']->id,
            'normalized_payload' => [
                'retailer_external_id' => 'new-external-id',
                'title' => 'New retailer listing',
                'current_price' => '79.99',
                'currency' => 'EUR',
                'sizes' => [
                    [
                        'eu_size' => $context['size']->label,
                        'in_stock' => true,
                        'price' => null,
                    ],
                ],
                'active' => true,
                'observed_at' => '2026-07-30T09:00:00+03:00',
            ],
            'raw_payload' => ['source' => 'new-preview-test'],
        ]);

        $preview = app(FeedImportChangePreview::class)->build($item);
        $fields = collect($preview['fields'])->keyBy('label');
        $sizes = collect($preview['sizes'])->keyBy('label');

        $this->assertTrue($preview['will_apply']);
        $this->assertStringContainsString(
            'new retailer listing',
            $preview['summary'],
        );
        $this->assertSame(
            'Not provided',
            $fields['Retailer external ID']['current'],
        );
        $this->assertSame(
            'new-external-id',
            $fields['Retailer external ID']['incoming'],
        );
        $this->assertSame('Not provided', $fields['Current price']['current']);
        $this->assertSame('79.99 EUR', $fields['Current price']['incoming']);
        $this->assertSame('Not listed', $sizes['EU 42']['current']);
        $this->assertSame(
            'In stock, listing price',
            $sizes['EU 42']['incoming'],
        );
        $this->assertSame(0, $context['retailer']->listings()->count());
    }

    public function test_preview_explains_ignored_rows_without_listing_changes(): void
    {
        $context = $this->createCatalogueContext('feed-change-ignored');
        $feedImport = $this->createFeedImport($context);
        $item = $feedImport->items()->create([
            'source_record' => 2,
            'identity' => 'CHANGE-PREVIEW-IGNORED',
            'outcome' => 'manual_review',
            'reason' => 'no_strong_match',
            'normalized_payload' => ['title' => 'Ignored shoe'],
            'raw_payload' => ['title' => 'Ignored shoe'],
            'resolution' => FeedImportItem::RESOLUTION_IGNORE,
        ]);

        $preview = app(FeedImportChangePreview::class)->build($item);

        $this->assertFalse($preview['will_apply']);
        $this->assertStringContainsString('ignored', $preview['summary']);
        $this->assertSame([], $preview['fields']);
        $this->assertSame([], $preview['sizes']);
    }

    private function createFeedImport(array $context): FeedImport
    {
        return FeedImport::create([
            'retailer_id' => $context['retailer']->id,
            'original_filename' => 'change-preview.csv',
            'stored_path' => 'feed-imports/change-preview.csv',
            'format' => 'csv',
            'status' => FeedImport::STATUS_READY,
        ]);
    }
}
