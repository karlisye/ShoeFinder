<?php

namespace Tests\Feature\Database;

use App\Enums\Audience;
use App\Enums\ImageSourceType;
use App\Enums\ListingSourceType;
use App\Enums\SiteLocale;
use App\Models\OutboundClick;
use App\Models\ShoeImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCatalogueData;
use Tests\TestCase;

class StageTwoRelationshipsTest extends TestCase
{
    use CreatesCatalogueData;
    use RefreshDatabase;

    public function test_catalogue_models_expose_the_stage_one_relationships_and_casts(): void
    {
        $context = $this->createCatalogueContext();
        $listing = $this->createListing(
            $context['variant'],
            $context['retailer'],
        );
        $listingSize = $this->createListingSize(
            $listing,
            $context['size'],
        );
        $image = ShoeImage::create([
            'shoe_variant_id' => $context['variant']->id,
            'source_type' => 'local',
            'path' => 'shoes/example.webp',
            'sort_order' => 0,
            'is_primary' => true,
        ]);
        $click = OutboundClick::create([
            'retailer_listing_id' => $listing->id,
            'locale' => 'lv',
            'referrer_path' => '/apavi/shoe-default',
            'clicked_at' => '2026-07-27 12:30:00',
        ]);

        $this->assertTrue($context['brand']->shoes->first()->is($context['shoe']));
        $this->assertTrue($context['category']->shoes->first()->is($context['shoe']));
        $this->assertTrue($context['shoe']->brand->is($context['brand']));
        $this->assertTrue($context['shoe']->category->is($context['category']));
        $this->assertTrue($context['shoe']->variants->first()->is($context['variant']));
        $this->assertTrue($context['colour']->variants->first()->is($context['variant']));
        $this->assertTrue($context['variant']->shoe->is($context['shoe']));
        $this->assertTrue($context['variant']->colour->is($context['colour']));
        $this->assertTrue($context['variant']->images->first()->is($image));
        $this->assertTrue($image->variant->is($context['variant']));
        $this->assertTrue($context['variant']->retailerListings->first()->is($listing));
        $this->assertTrue($context['retailer']->listings->first()->is($listing));
        $this->assertTrue($listing->variant->is($context['variant']));
        $this->assertTrue($listing->retailer->is($context['retailer']));
        $this->assertTrue($listing->listingSizes->first()->is($listingSize));
        $this->assertTrue($listingSize->retailerListing->is($listing));
        $this->assertTrue($context['size']->listingSizes->first()->is($listingSize));
        $this->assertTrue($listingSize->size->is($context['size']));
        $this->assertTrue($listing->priceChanges->first()->retailerListing->is($listing));
        $this->assertTrue($listing->outboundClicks->first()->is($click));
        $this->assertTrue($click->retailerListing->is($listing));

        $this->assertSame(Audience::Unisex, $context['shoe']->audience);
        $this->assertSame(ImageSourceType::Local, $image->source_type);
        $this->assertSame(ListingSourceType::Manual, $listing->source_type);
        $this->assertSame(SiteLocale::Latvian, $click->locale);
        $this->assertSame(['source' => 'test'], $listing->raw_payload);
        $this->assertSame('99.99', $listing->current_price);
        $this->assertSame('42.0', $context['size']->eu_size);
        $this->assertTrue($listing->active);
        $this->assertTrue($listingSize->in_stock);
    }
}
