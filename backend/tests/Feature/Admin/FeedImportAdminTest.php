<?php

namespace Tests\Feature\Admin;

use App\Enums\Audience;
use App\Filament\Resources\FeedImports\FeedImportResource;
use App\Filament\Resources\FeedImports\Pages\CreateFeedImport;
use App\Filament\Resources\FeedImports\Pages\EditFeedImport;
use App\Filament\Resources\FeedImports\RelationManagers\ItemsRelationManager;
use App\Models\Colour;
use App\Models\FeedImport;
use App\Models\FeedImportItem;
use App\Models\FilterColour;
use App\Models\User;
use Database\Seeders\SizeSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesCatalogueData;
use Tests\TestCase;

class FeedImportAdminTest extends TestCase
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
        Storage::fake('local');
    }

    public function test_import_list_create_and_review_pages_render(): void
    {
        $context = $this->createCatalogueContext('feed-admin-pages');
        $context['retailer']->update(['slug' => 'sole-market']);
        $feedImport = $this->createFeedImport($context);

        $this->get(FeedImportResource::getUrl('index'))->assertOk();
        $this->get(FeedImportResource::getUrl('create'))->assertOk();
        $this->get(FeedImportResource::getUrl('edit', [
            'record' => $feedImport,
        ]))
            ->assertOk()
            ->assertSee('test.csv');
    }

    public function test_review_action_persists_an_ignore_decision(): void
    {
        $context = $this->createCatalogueContext('feed-admin-review');
        $context['retailer']->update(['slug' => 'sole-market']);
        $feedImport = $this->createFeedImport($context);
        $item = $feedImport->items()->create([
            'source_record' => 2,
            'identity' => 'REVIEW-1',
            'outcome' => 'manual_review',
            'reason' => 'strong_identity_conflict',
            'normalized_payload' => ['title' => 'Review shoe'],
            'raw_payload' => ['title' => 'Review shoe'],
        ]);

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $feedImport,
            'pageClass' => EditFeedImport::class,
        ])
            ->callAction(
                TestAction::make('review')->table($item),
                [
                    'resolution' => FeedImportItem::RESOLUTION_IGNORE,
                ],
            )
            ->assertHasNoActionErrors()
            ->assertDispatched('refresh-page');

        $this->assertSame(
            FeedImportItem::RESOLUTION_IGNORE,
            $item->refresh()->resolution,
        );
        $this->assertNotNull($item->resolved_at);
        $this->assertSame(auth()->id(), $item->resolved_by);
        $this->assertTrue($feedImport->refresh()->canApply());
    }

    public function test_view_changes_action_renders_listing_and_size_differences(): void
    {
        $context = $this->createCatalogueContext('feed-admin-changes');
        $listing = $this->createListing(
            $context['variant'],
            $context['retailer'],
        );
        $this->createListingSize($listing, $context['size']);
        $feedImport = $this->createFeedImport($context);
        $item = $feedImport->items()->create([
            'source_record' => 2,
            'identity' => 'ADMIN-CHANGES-1',
            'outcome' => 'updated',
            'reason' => 'retailer_identity',
            'matched_listing_id' => $listing->id,
            'matched_variant_id' => $context['variant']->id,
            'normalized_payload' => [
                'current_price' => '89.99',
                'currency' => 'EUR',
                'sizes' => [
                    [
                        'eu_size' => $context['size']->label,
                        'in_stock' => false,
                        'price' => null,
                    ],
                ],
                'active' => true,
                'observed_at' => '2026-07-30T09:00:00+03:00',
            ],
            'raw_payload' => ['source' => 'admin-change-test'],
        ]);

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $feedImport,
            'pageClass' => EditFeedImport::class,
        ])
            ->assertActionExists(
                TestAction::make('viewChanges')->table($item),
            )
            ->mountAction(TestAction::make('viewChanges')->table($item))
            ->assertHasNoActionErrors();

        $this->assertSame('99.99', $listing->fresh()->current_price);
        $this->assertTrue(
            $listing->listingSizes()->firstOrFail()->in_stock,
        );
    }

    public function test_review_action_can_confirm_a_matched_listing_identity_update(): void
    {
        $context = $this->createCatalogueContext('feed-admin-identity-update');
        $context['retailer']->update(['slug' => 'sole-market']);
        $listing = $this->createListing(
            $context['variant'],
            $context['retailer'],
        );
        $feedImport = $this->createFeedImport($context);
        $item = $feedImport->items()->create([
            'source_record' => 2,
            'identity' => 'IDENTITY-UPDATE-1',
            'outcome' => 'manual_review',
            'reason' => 'strong_identity_conflict',
            'matched_listing_id' => $listing->id,
            'matched_variant_id' => $context['variant']->id,
            'normalized_payload' => [
                'title' => 'Updated retailer title',
                'retailer_external_id' => 'updated-external-id',
                'retailer_sku' => 'updated-sku',
                'manufacturer_variant_code' => 'UPDATED-VARIANT',
            ],
            'raw_payload' => ['title' => 'Updated retailer title'],
        ]);

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $feedImport,
            'pageClass' => EditFeedImport::class,
        ])
            ->mountAction(TestAction::make('review')->table($item))
            ->assertFormFieldExists(
                'resolution',
                'mountedActionSchema0',
                fn (Select $field): bool => $field->getOptions()[
                    FeedImportItem::RESOLUTION_UPDATE_MATCHED
                ] === 'Update matched listing',
            );

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $feedImport,
            'pageClass' => EditFeedImport::class,
        ])
            ->callAction(
                TestAction::make('review')->table($item),
                [
                    'resolution' => FeedImportItem::RESOLUTION_UPDATE_MATCHED,
                    'confirm_identity_update' => true,
                ],
            )
            ->assertHasNoActionErrors();

        $this->assertSame(
            FeedImportItem::RESOLUTION_UPDATE_MATCHED,
            $item->fresh()->resolution,
        );
        $this->assertTrue($feedImport->fresh()->canApply());
    }

    public function test_upload_creates_a_persisted_preview_without_catalogue_writes(): void
    {
        $context = $this->createCatalogueContext('feed-admin-upload');
        $context['size']->update(['sort_order' => 1000]);
        $this->seed(SizeSeeder::class);
        $context['retailer']->update(['slug' => 'sole-market']);
        $lines = file(
            base_path('tests/Fixtures/ProductFeeds/clean/sole-market.csv'),
            FILE_IGNORE_NEW_LINES,
        );
        $file = UploadedFile::fake()->createWithContent(
            'sole-market.csv',
            implode("\n", [$lines[0], $lines[1]])."\n",
        );

        Livewire::test(CreateFeedImport::class)
            ->fillForm([
                'retailer_id' => $context['retailer']->id,
                'stored_path' => $file,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $feedImport = FeedImport::query()->firstOrFail();

        $this->assertSame(
            FeedImport::STATUS_READY,
            $feedImport->status,
            json_encode($feedImport->errors),
        );
        $this->assertSame('sole-market.csv', $feedImport->original_filename);
        $this->assertSame(1, $feedImport->total_count);
        $this->assertSame(0, $context['retailer']->listings()->count());
        Storage::disk('local')->assertExists($feedImport->stored_path);
    }

    #[DataProvider('supportedFeedUploads')]
    public function test_upload_accepts_every_supported_feed_format(
        string $retailerSlug,
        string $fixture,
        string $mimeType,
        string $expectedFormat,
    ): void {
        $context = $this->createCatalogueContext(
            'feed-admin-format-'.$expectedFormat,
        );
        $context['size']->update(['sort_order' => 1000]);
        $this->seed(SizeSeeder::class);
        $context['retailer']->update(['slug' => $retailerSlug]);
        $file = UploadedFile::fake()->createWithContent(
            $fixture,
            file_get_contents(
                base_path("tests/Fixtures/ProductFeeds/clean/{$fixture}"),
            ),
        )->mimeType($mimeType);

        Livewire::test(CreateFeedImport::class)
            ->fillForm([
                'retailer_id' => $context['retailer']->id,
                'stored_path' => $file,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $feedImport = FeedImport::query()->firstOrFail();

        $this->assertSame($expectedFormat, $feedImport->format);
        $this->assertSame(FeedImport::STATUS_READY, $feedImport->status);
        Storage::disk('local')->assertExists($feedImport->stored_path);
    }

    public function test_review_action_saves_new_colour_variant_data(): void
    {
        $context = $this->createCatalogueContext('feed-admin-new-colour');
        $context['retailer']->update(['slug' => 'sole-market']);
        $feedImport = $this->createFeedImport($context);
        $item = $feedImport->items()->create([
            'source_record' => 2,
            'identity' => 'NEW-COLOUR-1',
            'outcome' => 'manual_review',
            'reason' => 'no_strong_match',
            'normalized_payload' => [
                'title' => 'Review shoe',
                'colour' => 'Sail/Black',
                'manufacturer_variant_code' => 'REVIEW-200',
            ],
            'raw_payload' => ['title' => 'Review shoe'],
        ]);

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $feedImport,
            'pageClass' => EditFeedImport::class,
        ])
            ->callAction(
                TestAction::make('review')->table($item),
                [
                    'resolution' => FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT,
                    'selected_variant_id' => $context['variant']->id,
                    'new_colour_code' => 'sail-black-review',
                    'new_colour_name' => 'Sail/Black',
                    'new_filter_colour_ids' => $this->filterColourIds([
                        'black',
                        'beige',
                    ]),
                    'new_manufacturer_variant_code' => 'REVIEW-200',
                ],
            )
            ->assertHasNoActionErrors();

        $item->refresh();

        $this->assertSame(
            FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT,
            $item->resolution,
        );
        $this->assertSame($context['variant']->id, $item->selected_variant_id);
        $this->assertSame('sail-black-review', $item->new_colour_code);
        $this->assertSame('Sail/Black', $item->new_colour_name);
        $this->assertSame(
            $this->filterColourIds(['black', 'beige']),
            $item->new_filter_colour_ids,
        );
        $this->assertSame('REVIEW-200', $item->new_manufacturer_variant_code);
        $this->assertSame(1, $context['shoe']->variants()->count());
    }

    public function test_review_action_can_select_an_existing_shared_colour(): void
    {
        $context = $this->createCatalogueContext('feed-admin-existing-colour');
        $context['retailer']->update(['slug' => 'sole-market']);
        $existingColour = Colour::create([
            'code' => 'white-black',
            'name' => 'White/Black',
        ]);
        $existingColour->filterColours()->attach(
            $this->filterColourIds(['black', 'white']),
        );
        $feedImport = $this->createFeedImport($context);
        $item = $feedImport->items()->create([
            'source_record' => 2,
            'identity' => 'EXISTING-COLOUR-1',
            'outcome' => 'manual_review',
            'reason' => 'no_strong_match',
            'normalized_payload' => [
                'title' => 'Nike Air Max 270',
                'colour' => 'White/Black',
                'manufacturer_variant_code' => 'AH8050-100',
            ],
            'raw_payload' => ['title' => 'Nike Air Max 270'],
        ]);

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $feedImport,
            'pageClass' => EditFeedImport::class,
        ])
            ->callAction(
                TestAction::make('review')->table($item),
                [
                    'resolution' => FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT,
                    'selected_variant_id' => $context['variant']->id,
                    'selected_colour_id' => $existingColour->id,
                    'new_manufacturer_variant_code' => 'AH8050-100',
                ],
            )
            ->assertHasNoActionErrors();

        $item->refresh();

        $this->assertSame($existingColour->id, $item->selected_colour_id);
        $this->assertNull($item->new_colour_code);
        $this->assertSame(1, $context['shoe']->variants()->count());
    }

    public function test_review_action_can_select_a_pending_shared_colour(): void
    {
        $context = $this->createCatalogueContext('feed-admin-pending-colour');
        $context['retailer']->update(['slug' => 'sole-market']);
        $feedImport = $this->createFeedImport($context);
        $pendingItem = $feedImport->items()->create([
            'source_record' => 2,
            'identity' => 'PENDING-COLOUR-1',
            'outcome' => 'manual_review',
            'reason' => 'no_strong_match',
            'normalized_payload' => ['title' => 'Puma Suede XL'],
            'raw_payload' => ['title' => 'Puma Suede XL'],
            'resolution' => FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT,
            'new_colour_code' => 'black-white',
            'new_colour_name' => 'Black/White',
            'new_filter_colour_ids' => $this->filterColourIds([
                'black',
                'white',
            ]),
            'resolved_at' => now(),
        ]);
        $item = $feedImport->items()->create([
            'source_record' => 3,
            'identity' => 'PENDING-COLOUR-2',
            'outcome' => 'manual_review',
            'reason' => 'no_strong_match',
            'normalized_payload' => ['title' => 'Vans Old Skool'],
            'raw_payload' => ['title' => 'Vans Old Skool'],
        ]);
        $pendingOption = "pending:{$pendingItem->id}";

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $feedImport,
            'pageClass' => EditFeedImport::class,
        ])
            ->mountAction(TestAction::make('review')->table($item))
            ->assertFormFieldExists(
                'selected_colour_id',
                'mountedActionSchema0',
                fn (Select $field): bool => $field->getOptions()[$pendingOption]
                    === 'Black/White (black-white, pending import)',
            );

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $feedImport,
            'pageClass' => EditFeedImport::class,
        ])
            ->callAction(
                TestAction::make('review')->table($item),
                [
                    'resolution' => FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT,
                    'selected_colour_id' => $pendingOption,
                    'new_shoe_brand_id' => $context['brand']->id,
                    'new_shoe_category_id' => $context['category']->id,
                    'new_shoe_name' => 'Old Skool',
                    'new_shoe_slug' => 'old-skool-pending-colour',
                    'new_shoe_audience' => Audience::Unisex->value,
                ],
            )
            ->assertHasNoActionErrors();

        $item->refresh();

        $this->assertNull($item->selected_colour_id);
        $this->assertSame('black-white', $item->new_colour_code);
        $this->assertSame('Black/White', $item->new_colour_name);
        $this->assertSame(
            $this->filterColourIds(['black', 'white']),
            $item->new_filter_colour_ids,
        );
    }

    public function test_apply_action_locks_the_import_after_completion(): void
    {
        $context = $this->createCatalogueContext('feed-admin-apply');
        $context['retailer']->update(['slug' => 'sole-market']);
        $feedImport = $this->createFeedImport($context);

        Livewire::test(EditFeedImport::class, [
            'record' => $feedImport->getRouteKey(),
        ])
            ->callAction('apply')
            ->assertNotified('Data imported');

        $this->assertSame(
            FeedImport::STATUS_APPLIED,
            $feedImport->refresh()->status,
        );
    }

    public function test_review_action_saves_new_shoe_data(): void
    {
        $context = $this->createCatalogueContext('feed-admin-new-shoe');
        $context['retailer']->update(['slug' => 'sole-market']);
        $feedImport = $this->createFeedImport($context);
        $item = $feedImport->items()->create([
            'source_record' => 2,
            'identity' => 'NEW-SHOE-1',
            'outcome' => 'manual_review',
            'reason' => 'no_strong_match',
            'normalized_payload' => [
                'title' => 'Nike New Runner',
                'brand' => 'Nike',
                'colour' => 'Blue/White',
                'manufacturer_style_code' => 'NR100',
                'manufacturer_variant_code' => 'NR100-400',
            ],
            'raw_payload' => ['title' => 'Nike New Runner'],
        ]);

        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $feedImport,
            'pageClass' => EditFeedImport::class,
        ])
            ->callAction(
                TestAction::make('review')->table($item),
                [
                    'resolution' => FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT,
                    'new_shoe_brand_id' => $context['brand']->id,
                    'new_shoe_category_id' => $context['category']->id,
                    'new_shoe_name' => 'New Runner',
                    'new_shoe_slug' => 'new-runner-import',
                    'new_shoe_style_code' => 'NR100',
                    'new_shoe_audience' => Audience::Unisex->value,
                    'new_colour_code' => 'blue-white-import',
                    'new_colour_name' => 'Blue/White',
                    'new_filter_colour_ids' => $this->filterColourIds([
                        'white',
                        'blue',
                    ]),
                    'new_manufacturer_variant_code' => 'NR100-400',
                ],
            )
            ->assertHasNoActionErrors();

        $item->refresh();

        $this->assertSame(
            FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT,
            $item->resolution,
        );
        $this->assertSame($context['brand']->id, $item->new_shoe_brand_id);
        $this->assertSame($context['category']->id, $item->new_shoe_category_id);
        $this->assertSame('New Runner', $item->new_shoe_name);
        $this->assertSame('new-runner-import', $item->new_shoe_slug);
        $this->assertSame(Audience::Unisex->value, $item->new_shoe_audience);
        $this->assertDatabaseMissing('shoes', [
            'slug' => 'new-runner-import',
        ]);
    }

    private function createFeedImport(array $context): FeedImport
    {
        return FeedImport::create([
            'retailer_id' => $context['retailer']->id,
            'user_id' => auth()->id(),
            'original_filename' => 'test.csv',
            'stored_path' => 'feed-imports/test.csv',
            'format' => 'csv',
            'status' => FeedImport::STATUS_READY,
        ]);
    }

    private function filterColourIds(array $codes): array
    {
        return FilterColour::query()
            ->whereIn('code', $codes)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    public static function supportedFeedUploads(): array
    {
        return [
            'CSV' => [
                'sole-market',
                'sole-market.csv',
                'text/csv',
                'csv',
            ],
            'JSON' => [
                'urban-step',
                'urban-step.json',
                'application/json',
                'json',
            ],
            'JSON Lines' => [
                'sneaker-point',
                'sneaker-point.jsonl',
                'application/x-ndjson',
                'jsonl',
            ],
            'XML' => [
                'apavu-nams',
                'apavu-nams.xml',
                'application/xml',
                'xml',
            ],
        ];
    }
}
