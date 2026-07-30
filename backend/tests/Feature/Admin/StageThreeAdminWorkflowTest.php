<?php

namespace Tests\Feature\Admin;

use App\Enums\Audience;
use App\Enums\ImageSourceType;
use App\Enums\ListingSourceType;
use App\Filament\Resources\Brands\Pages\CreateBrand;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Retailers\Pages\CreateRetailer;
use App\Filament\Resources\Shoes\Pages\CreateShoe;
use App\Filament\Resources\Shoes\Pages\EditShoe;
use App\Filament\Resources\Shoes\RelationManagers\VariantsRelationManager;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Colour;
use App\Models\OutboundClick;
use App\Models\Retailer;
use App\Models\Shoe;
use App\Models\Size;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\CreatesCatalogueData;
use Tests\TestCase;

class StageThreeAdminWorkflowTest extends TestCase
{
    use CreatesCatalogueData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.admin_email' => 'admin@example.test']);

        $this->actingAs(User::factory()->create([
            'email' => 'admin@example.test',
        ]));

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('public');
    }

    public function test_top_level_resources_create_manual_catalogue_records(): void
    {
        Livewire::test(CreateBrand::class)
            ->fillForm([
                'name' => 'New Balance',
                'slug' => 'new-balance',
                'website_url' => 'https://www.newbalance.com',
                'active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'slug' => 'skrienasanas-apavi',
                'name_lv' => 'Skriešanas apavi',
                'name_en' => 'Running shoes',
                'sort_order' => 10,
                'active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(CreateRetailer::class)
            ->fillForm([
                'name' => 'Sporta veikals',
                'slug' => 'sporta-veikals',
                'website_url' => 'https://example.com',
                'active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $brand = Brand::where('slug', 'new-balance')->firstOrFail();
        $category = Category::where('slug', 'skrienasanas-apavi')->firstOrFail();

        Livewire::test(CreateShoe::class)
            ->fillForm([
                'brand_id' => $brand->id,
                'category_id' => $category->id,
                'name' => 'Fresh Foam 1080v14',
                'slug' => 'fresh-foam-1080v14',
                'manufacturer_style_code' => 'M1080V14',
                'audience' => Audience::Men->value,
                'description_lv' => 'Ikdienas skriešanas apavi.',
                'description_en' => 'Daily running shoes.',
                'active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Brand::class, [
            'slug' => 'new-balance',
        ]);
        $this->assertDatabaseHas(Category::class, [
            'slug' => 'skrienasanas-apavi',
        ]);
        $this->assertDatabaseHas(Retailer::class, [
            'slug' => 'sporta-veikals',
        ]);
        $this->assertDatabaseHas(Shoe::class, [
            'slug' => 'fresh-foam-1080v14',
        ]);
    }

    public function test_shoe_resource_creates_a_complete_nested_variant(): void
    {
        $brand = Brand::create([
            'name' => 'ASICS',
            'slug' => 'asics',
            'active' => true,
        ]);
        $category = Category::create([
            'slug' => 'skrienasanas-apavi',
            'name_lv' => 'Skriešanas apavi',
            'name_en' => 'Running shoes',
            'sort_order' => 10,
            'active' => true,
        ]);
        $retailer = Retailer::create([
            'name' => 'Skrējēju veikals',
            'slug' => 'skrejeju-veikals',
            'active' => true,
        ]);
        $colour = Colour::create([
            'code' => 'blue-test',
            'name' => 'Blue',
            'sort_order' => 1,
            'active' => true,
        ]);
        $size = Size::create([
            'eu_size' => 42,
            'label' => '42',
            'sort_order' => 52,
            'active' => true,
        ]);
        $shoe = Shoe::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name' => 'Gel-Kayano 31',
            'slug' => 'gel-kayano-31',
            'audience' => Audience::Men,
            'active' => true,
        ]);

        $relationManager = Livewire::test(VariantsRelationManager::class, [
            'ownerRecord' => $shoe,
            'pageClass' => EditShoe::class,
        ]);

        $relationManager
            ->callAction(
                TestAction::make('create')->table(),
                [
                    'colour_id' => $colour->id,
                    'manufacturer_variant_code' => '1011B867-400',
                    'active' => true,
                ],
            )
            ->assertHasNoActionErrors();

        $variant = $shoe->variants()->firstOrFail();

        $relationManager
            ->callAction(
                TestAction::make('edit')->table($variant),
                [
                    'colour_id' => $colour->id,
                    'manufacturer_variant_code' => '1011B867-400',
                    'active' => true,
                    'images' => [
                        [
                            'source_type' => ImageSourceType::External->value,
                            'external_url' => 'https://images.example.com/kayano-blue.jpg',
                            'alt_text_lv' => 'ASICS Gel-Kayano 31 zilā krāsā',
                            'alt_text_en' => 'ASICS Gel-Kayano 31 in blue',
                            'sort_order' => 0,
                            'is_primary' => true,
                        ],
                    ],
                    'retailerListings' => [
                        [
                            'retailer_id' => $retailer->id,
                            'product_url' => 'https://example.com/gel-kayano-31',
                            'affiliate_url' => 'https://example.com/go/gel-kayano-31',
                            'source_type' => ListingSourceType::Manual->value,
                            'current_price' => 159.99,
                            'original_price' => 179.99,
                            'currency' => 'EUR',
                            'delivery_cost' => 3.99,
                            'delivery_min_days' => 1,
                            'delivery_max_days' => 3,
                            'active' => true,
                            'last_checked_at' => now(),
                            'retailer_external_id' => 'KAYANO-31-BLUE',
                            'retailer_sku' => 'SKU-1001',
                            'gtin' => '1234567890123',
                            'raw_title' => 'ASICS Gel-Kayano 31 Blue',
                            'raw_colour' => 'Blue Expanse',
                            'raw_payload' => ['source' => 'manual-test'],
                            'listingSizes' => [
                                [
                                    'size_id' => $size->id,
                                    'in_stock' => true,
                                    'price' => 154.99,
                                ],
                            ],
                        ],
                    ],
                ],
            )
            ->assertHasNoActionErrors();

        $variant->refresh();
        $listing = $variant->retailerListings()->firstOrFail();

        $this->assertSame($colour->id, $variant->colour_id);
        $this->assertSame(1, $variant->images()->count());
        $this->assertSame($retailer->id, $listing->retailer_id);
        $this->assertSame(1, $listing->listingSizes()->count());
        $this->assertSame('154.99', $listing->listingSizes()->firstOrFail()->effectivePrice());
        $this->assertDatabaseHas('price_changes', [
            'retailer_listing_id' => $listing->id,
            'price' => 159.99,
            'original_price' => 179.99,
        ]);
    }

    public function test_variant_colour_can_be_created_from_the_colour_selector(): void
    {
        $context = $this->createCatalogueContext('inline-colour');

        $relationManager = Livewire::test(VariantsRelationManager::class, [
            'ownerRecord' => $context['shoe'],
            'pageClass' => EditShoe::class,
        ])->mountAction(TestAction::make('create')->table());

        $relationManager
            ->callFormComponentAction(
                'colour_id',
                'createOption',
                [
                    'code' => 'burgundy-test',
                    'name' => 'Burgundy',
                ],
                formName: 'mountedActionSchema0',
            )
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $colour = Colour::where('code', 'burgundy-test')->firstOrFail();

        $this->assertDatabaseHas('colours', [
            'id' => $colour->id,
            'name' => 'Burgundy',
            'sort_order' => 0,
            'active' => true,
        ]);
        $this->assertDatabaseHas('shoe_variants', [
            'shoe_id' => $context['shoe']->id,
            'colour_id' => $colour->id,
        ]);
    }

    public function test_duplicate_variant_colour_and_code_show_validation_errors(): void
    {
        $context = $this->createCatalogueContext('duplicate-variant');

        Livewire::test(VariantsRelationManager::class, [
            'ownerRecord' => $context['shoe'],
            'pageClass' => EditShoe::class,
        ])
            ->callAction(
                TestAction::make('create')->table(),
                [
                    'colour_id' => $context['colour']->id,
                    'manufacturer_variant_code' => $context['variant']
                        ->manufacturer_variant_code,
                    'active' => true,
                ],
            )
            ->assertHasActionErrors([
                'colour_id' => 'unique',
                'manufacturer_variant_code' => 'unique',
            ]);

        $this->assertSame(1, $context['shoe']->variants()->count());
    }

    public function test_listing_price_edits_keep_change_only_history(): void
    {
        $context = $this->createCatalogueContext('admin-price');
        $listing = $this->createListing(
            $context['variant'],
            $context['retailer'],
        );
        $this->createListingSize($listing, $context['size']);

        $priceEdit = Livewire::test(VariantsRelationManager::class, [
            'ownerRecord' => $context['shoe'],
            'pageClass' => EditShoe::class,
        ])->mountAction(
            TestAction::make('edit')->table($context['variant']),
        );

        $listingKey = array_key_first(
            $priceEdit->get('mountedActions')[0]['data']['retailerListings'],
        );

        $priceEdit
            ->set(
                "mountedActions.0.data.retailerListings.{$listingKey}.current_price",
                89.99,
            )
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(2, $listing->priceChanges()->count());
        $this->assertSame(
            '89.99',
            $listing->refresh()->current_price,
        );

        $deliveryEdit = Livewire::test(VariantsRelationManager::class, [
            'ownerRecord' => $context['shoe'],
            'pageClass' => EditShoe::class,
        ])->mountAction(
            TestAction::make('edit')->table($context['variant']),
        );

        $listingKey = array_key_first(
            $deliveryEdit->get('mountedActions')[0]['data']['retailerListings'],
        );

        $deliveryEdit
            ->set(
                "mountedActions.0.data.retailerListings.{$listingKey}.delivery_cost",
                4.99,
            )
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(2, $listing->priceChanges()->count());
        $this->assertSame(
            '4.99',
            $listing->refresh()->delivery_cost,
        );
    }

    public function test_sizes_can_be_added_quickly_with_the_offer_price(): void
    {
        $context = $this->createCatalogueContext('admin-quick-size');
        $listing = $this->createListing(
            $context['variant'],
            $context['retailer'],
            ['current_price' => 89.99],
        );
        $this->createListingSize($listing, $context['size']);
        $additionalSize = Size::create([
            'eu_size' => 43,
            'label' => '43',
            'sort_order' => 54,
            'active' => true,
        ]);

        $edit = Livewire::test(VariantsRelationManager::class, [
            'ownerRecord' => $context['shoe'],
            'pageClass' => EditShoe::class,
        ])->mountAction(
            TestAction::make('edit')->table($context['variant']),
        );

        $listingKey = array_key_first(
            $edit->get('mountedActions')[0]['data']['retailerListings'],
        );
        $quickSizePath = "mountedActions.0.data.retailerListings.{$listingKey}.quick_size_ids";

        $this->assertContains(
            (string) $context['size']->id,
            $edit->get($quickSizePath),
        );

        $edit
            ->set(
                $quickSizePath,
                [
                    (string) $context['size']->id,
                    (string) $additionalSize->id,
                ],
            )
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $quickSize = $listing->listingSizes()
            ->where('size_id', $additionalSize->id)
            ->firstOrFail();

        $this->assertTrue($quickSize->in_stock);
        $this->assertNull($quickSize->price);
        $this->assertSame('89.99', $quickSize->effectivePrice());
        $this->assertSame(2, $listing->listingSizes()->count());
    }

    public function test_offer_can_be_deleted_with_its_dependent_records(): void
    {
        $context = $this->createCatalogueContext('admin-delete-offer');
        $listing = $this->createListing(
            $context['variant'],
            $context['retailer'],
        );
        $listingSize = $this->createListingSize($listing, $context['size']);
        $click = OutboundClick::create([
            'retailer_listing_id' => $listing->id,
            'locale' => 'lv',
            'referrer_path' => '/catalogue',
            'clicked_at' => now(),
        ]);
        $priceChangeId = $listing->priceChanges()->firstOrFail()->id;

        Livewire::test(VariantsRelationManager::class, [
            'ownerRecord' => $context['shoe'],
            'pageClass' => EditShoe::class,
        ])
            ->mountAction(
                TestAction::make('edit')->table($context['variant']),
            )
            ->set('mountedActions.0.data.retailerListings', [])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseMissing('retailer_listings', ['id' => $listing->id]);
        $this->assertDatabaseMissing('retailer_listing_sizes', ['id' => $listingSize->id]);
        $this->assertDatabaseMissing('price_changes', ['id' => $priceChangeId]);
        $this->assertDatabaseMissing('outbound_clicks', ['id' => $click->id]);
    }

    public function test_unauthorized_user_cannot_enter_the_panel(): void
    {
        $unauthorized = User::factory()->create([
            'email' => 'other@example.test',
        ]);

        $this->actingAs($unauthorized)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_configured_administrator_can_enter_the_panel(): void
    {
        $this->get('/admin')
            ->assertOk()
            ->assertSee('Apavi');
    }
}
