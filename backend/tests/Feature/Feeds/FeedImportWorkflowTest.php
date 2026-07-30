<?php

namespace Tests\Feature\Feeds;

use App\Domain\Feeds\FeedImportWorkflow;
use App\Enums\Audience;
use App\Models\Colour;
use App\Models\FeedImport;
use App\Models\FeedImportItem;
use App\Models\FilterColour;
use App\Models\Shoe;
use Database\Seeders\SizeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\Support\CreatesCatalogueData;
use Tests\TestCase;

class FeedImportWorkflowTest extends TestCase
{
    use CreatesCatalogueData;
    use RefreshDatabase;

    public function test_preview_is_persisted_without_changing_catalogue_data(): void
    {
        $context = $this->feedContext();
        $feedImport = $this->createImport($context, $this->singleCsvRecord(0));

        app(FeedImportWorkflow::class)->preview($feedImport);

        $feedImport->refresh();

        $this->assertSame(FeedImport::STATUS_READY, $feedImport->status);
        $this->assertSame(1, $feedImport->total_count);
        $this->assertSame(1, $feedImport->ready_count);
        $this->assertSame(0, $feedImport->review_count);
        $this->assertSame(0, $context['retailer']->listings()->count());
        $this->assertDatabaseHas('feed_import_items', [
            'feed_import_id' => $feedImport->id,
            'source_record' => 2,
            'outcome' => 'created',
            'matched_variant_id' => $context['variant']->id,
        ]);
    }

    public function test_ready_preview_can_be_applied_once(): void
    {
        $context = $this->feedContext();
        $feedImport = $this->createImport($context, $this->singleCsvRecord(0));
        $workflow = app(FeedImportWorkflow::class);

        $workflow->preview($feedImport);
        $workflow->apply($feedImport);

        $this->assertSame(FeedImport::STATUS_APPLIED, $feedImport->refresh()->status);
        $this->assertNotNull($feedImport->applied_at);
        $this->assertSame(1, $context['retailer']->listings()->count());
        $this->assertSame(5, $context['retailer']->listings()->first()->listingSizes()->count());

        $this->expectException(LogicException::class);
        $workflow->apply($feedImport);
    }

    public function test_unmatched_record_requires_a_saved_review_decision(): void
    {
        $context = $this->feedContext();
        $feedImport = $this->createImport($context, $this->singleCsvRecord(1));
        $workflow = app(FeedImportWorkflow::class);

        $workflow->preview($feedImport);

        $feedImport->refresh();
        $item = $feedImport->items()->firstOrFail();

        $this->assertSame('manual_review', $item->outcome);
        $this->assertSame('no_strong_match', $item->reason);
        $this->assertFalse($feedImport->canApply());

        $workflow->resolve(
            $item,
            FeedImportItem::RESOLUTION_ATTACH,
            $context['variant']->id,
            null,
        );

        $this->assertTrue($feedImport->refresh()->canApply());

        $workflow->apply($feedImport);

        $this->assertSame(1, $context['retailer']->listings()->count());
        $this->assertSame(
            $context['variant']->id,
            $context['retailer']->listings()->first()->shoe_variant_id,
        );
    }

    public function test_invalid_preview_cannot_be_applied(): void
    {
        $context = $this->feedContext();
        $contents = file_get_contents(
            base_path('tests/Fixtures/ProductFeeds/invalid/sole-market-invalid.csv'),
        );
        $feedImport = $this->createImport($context, $contents);
        $workflow = app(FeedImportWorkflow::class);

        $workflow->preview($feedImport);

        $this->assertSame(FeedImport::STATUS_FAILED, $feedImport->refresh()->status);
        $this->assertGreaterThan(0, $feedImport->invalid_count);
        $this->assertNotEmpty($feedImport->errors);
        $this->assertSame(0, $context['retailer']->listings()->count());

        $this->expectException(LogicException::class);
        $workflow->apply($feedImport);
    }

    public function test_review_can_create_a_new_colour_variant_on_apply(): void
    {
        $context = $this->feedContext();
        $context['shoe']->update([
            'name' => 'Dunk Low Retro',
            'manufacturer_style_code' => 'DD1391',
        ]);
        $context['colour']->update([
            'name' => 'Black/White',
        ]);
        $context['variant']->update([
            'manufacturer_variant_code' => 'DD1391-001',
        ]);
        $feedImport = $this->createImport($context, $this->singleCsvRecord(1));
        $workflow = app(FeedImportWorkflow::class);

        $workflow->preview($feedImport);

        $item = $feedImport->items()->firstOrFail();

        $this->assertSame('manual_review', $item->outcome);

        $workflow->resolve(
            $item,
            FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT,
            $context['variant']->id,
            null,
            [
                'new_colour_code' => 'imported-white-black',
                'new_colour_name' => 'White/Black',
                'new_filter_colour_ids' => $this->filterColourIds([
                    'black',
                    'white',
                ]),
                'new_manufacturer_variant_code' => 'DD1391-100',
            ],
        );

        $this->assertSame(1, $context['shoe']->variants()->count());
        $this->assertDatabaseMissing('colours', [
            'code' => 'imported-white-black',
        ]);

        $workflow->apply($feedImport);

        $newVariant = $context['shoe']->variants()
            ->where('manufacturer_variant_code', 'DD1391-100')
            ->firstOrFail();

        $this->assertSame(2, $context['shoe']->variants()->count());
        $this->assertSame('imported-white-black', $newVariant->colour->code);
        $this->assertSame('White/Black', $newVariant->colour->name);
        $this->assertSame(
            ['black', 'white'],
            $newVariant->colour->filterColours()
                ->orderBy('code')
                ->pluck('code')
                ->all(),
        );
        $this->assertSame(
            $newVariant->id,
            $context['retailer']->listings()->firstOrFail()->shoe_variant_id,
        );
        $this->assertSame(
            $newVariant->id,
            $item->refresh()->created_variant_id,
        );
    }

    public function test_review_can_reuse_an_existing_colour_for_a_new_variant(): void
    {
        $context = $this->feedContext();
        $existingColour = Colour::create([
            'code' => 'white-black',
            'name' => 'White/Black',
        ]);
        $existingColour->filterColours()->attach(
            $this->filterColourIds(['black', 'white']),
        );
        $feedImport = $this->createImport($context, $this->singleCsvRecord(1));
        $workflow = app(FeedImportWorkflow::class);

        $workflow->preview($feedImport);

        $item = $feedImport->items()->firstOrFail();
        $colourCount = Colour::query()->count();

        $workflow->resolve(
            $item,
            FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT,
            $context['variant']->id,
            null,
            [
                'selected_colour_id' => $existingColour->id,
                'new_manufacturer_variant_code' => 'DD1391-100',
            ],
        );

        $this->assertSame($existingColour->id, $item->refresh()->selected_colour_id);
        $this->assertNull($item->new_colour_code);
        $this->assertSame($colourCount, Colour::query()->count());

        $workflow->apply($feedImport);

        $newVariant = $context['shoe']->variants()
            ->where('manufacturer_variant_code', 'DD1391-100')
            ->firstOrFail();

        $this->assertSame($existingColour->id, $newVariant->colour_id);
        $this->assertSame($colourCount, Colour::query()->count());
    }

    public function test_review_rejects_an_existing_colour_already_used_by_the_shoe(): void
    {
        $context = $this->feedContext();
        $feedImport = $this->createImport($context, $this->singleCsvRecord(1));
        $workflow = app(FeedImportWorkflow::class);

        $workflow->preview($feedImport);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('This shoe already has this colourway.');

        $workflow->resolve(
            $feedImport->items()->firstOrFail(),
            FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT,
            $context['variant']->id,
            null,
            [
                'selected_colour_id' => $context['colour']->id,
                'new_manufacturer_variant_code' => 'DD1391-100',
            ],
        );
    }

    public function test_review_can_create_a_new_shoe_and_first_variant_on_apply(): void
    {
        $context = $this->feedContext();
        $feedImport = $this->createImport($context, $this->singleCsvRecord(2));
        $workflow = app(FeedImportWorkflow::class);

        $workflow->preview($feedImport);

        $item = $feedImport->items()->firstOrFail();

        $this->assertSame('manual_review', $item->outcome);

        $workflow->resolve(
            $item,
            FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT,
            null,
            null,
            [
                'new_shoe_brand_id' => $context['brand']->id,
                'new_shoe_category_id' => $context['category']->id,
                'new_shoe_name' => 'Air Max 90',
                'new_shoe_slug' => 'air-max-90-import',
                'new_shoe_style_code' => 'CN8490',
                'new_shoe_audience' => Audience::Men->value,
                'new_colour_code' => 'imported-white-wolf-grey',
                'new_colour_name' => 'White/Wolf Grey',
                'new_filter_colour_ids' => $this->filterColourIds([
                    'white',
                    'grey',
                ]),
                'new_manufacturer_variant_code' => 'CN8490-100',
            ],
        );

        $this->assertDatabaseMissing('shoes', [
            'slug' => 'air-max-90-import',
        ]);

        $workflow->apply($feedImport);

        $shoe = Shoe::query()
            ->where('slug', 'air-max-90-import')
            ->firstOrFail();
        $variant = $shoe->variants()->firstOrFail();

        $this->assertSame($context['brand']->id, $shoe->brand_id);
        $this->assertSame($context['category']->id, $shoe->category_id);
        $this->assertSame(Audience::Men, $shoe->audience);
        $this->assertSame('CN8490', $shoe->manufacturer_style_code);
        $this->assertSame('imported-white-wolf-grey', $variant->colour->code);
        $this->assertSame('CN8490-100', $variant->manufacturer_variant_code);
        $this->assertSame(
            $variant->id,
            $context['retailer']->listings()->firstOrFail()->shoe_variant_id,
        );
        $this->assertSame($shoe->id, $item->refresh()->created_shoe_id);
        $this->assertSame($variant->id, $item->created_variant_id);
    }

    public function test_review_reuses_one_pending_colour_for_multiple_new_shoes(): void
    {
        $context = $this->feedContext();
        $feedImport = $this->createImport(
            $context,
            $this->csvRecords([2, 3]),
        );
        $workflow = app(FeedImportWorkflow::class);

        $workflow->preview($feedImport);

        [$firstItem, $secondItem] = $feedImport->items()
            ->orderBy('source_record')
            ->get()
            ->all();
        $sharedColour = [
            'new_colour_code' => 'shared-black-white',
            'new_colour_name' => 'Black/White',
            'new_filter_colour_ids' => $this->filterColourIds([
                'black',
                'white',
            ]),
        ];

        $workflow->resolve(
            $firstItem,
            FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT,
            null,
            null,
            [
                'new_shoe_brand_id' => $context['brand']->id,
                'new_shoe_category_id' => $context['category']->id,
                'new_shoe_name' => 'Shared Colour One',
                'new_shoe_slug' => 'shared-colour-one',
                'new_shoe_audience' => Audience::Unisex->value,
                'new_manufacturer_variant_code' => 'SHARED-ONE',
                ...$sharedColour,
            ],
        );
        $workflow->resolve(
            $secondItem,
            FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT,
            null,
            null,
            [
                'new_shoe_brand_id' => $context['brand']->id,
                'new_shoe_category_id' => $context['category']->id,
                'new_shoe_name' => 'Shared Colour Two',
                'new_shoe_slug' => 'shared-colour-two',
                'new_shoe_audience' => Audience::Unisex->value,
                'new_manufacturer_variant_code' => 'SHARED-TWO',
                ...$sharedColour,
            ],
        );

        $this->assertDatabaseMissing('colours', [
            'code' => 'shared-black-white',
        ]);

        $workflow->apply($feedImport);

        $colour = Colour::query()
            ->where('code', 'shared-black-white')
            ->firstOrFail();
        $colourIds = Shoe::query()
            ->whereIn('slug', ['shared-colour-one', 'shared-colour-two'])
            ->with('variants')
            ->get()
            ->flatMap->variants
            ->pluck('colour_id')
            ->unique();

        $this->assertSame('Black/White', $colour->name);
        $this->assertCount(1, $colourIds);
        $this->assertSame($colour->id, $colourIds->first());
        $this->assertSame(
            ['black', 'white'],
            $colour->filterColours()
                ->orderBy('code')
                ->pluck('code')
                ->all(),
        );
    }

    private function createImport(array $context, string $contents): FeedImport
    {
        Storage::disk('local')->put('feed-imports/test.csv', $contents);

        return FeedImport::create([
            'retailer_id' => $context['retailer']->id,
            'original_filename' => 'test.csv',
            'stored_path' => 'feed-imports/test.csv',
            'format' => 'csv',
            'status' => FeedImport::STATUS_UPLOADED,
        ]);
    }

    private function singleCsvRecord(int $recordIndex): string
    {
        return $this->csvRecords([$recordIndex]);
    }

    private function csvRecords(array $recordIndexes): string
    {
        $lines = file(
            base_path('tests/Fixtures/ProductFeeds/clean/sole-market.csv'),
            FILE_IGNORE_NEW_LINES,
        );

        return implode("\n", [
            $lines[0],
            ...array_map(
                fn (int $recordIndex): string => $lines[$recordIndex + 1],
                $recordIndexes,
            ),
        ])."\n";
    }

    private function feedContext(): array
    {
        $context = $this->createCatalogueContext('admin-feed-import');
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

    private function filterColourIds(array $codes): array
    {
        return FilterColour::query()
            ->whereIn('code', $codes)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }
}
