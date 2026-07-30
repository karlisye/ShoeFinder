<?php

namespace App\Domain\Feeds;

use App\Domain\Feeds\Data\FeedRecord;
use App\Enums\Audience;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Colour;
use App\Models\FeedImport;
use App\Models\FeedImportItem;
use App\Models\FilterColour;
use App\Models\RetailerListing;
use App\Models\Shoe;
use App\Models\ShoeVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Throwable;

class FeedImportWorkflow
{
    public function __construct(
        private readonly FeedImporter $importer,
        private readonly FeedListingSynchronizer $synchronizer,
    ) {}

    public function preview(FeedImport $feedImport): FeedImport
    {
        $path = Storage::disk('local')->path($feedImport->stored_path);

        try {
            $report = $this->importer->import(
                $feedImport->retailer,
                $feedImport->retailer->slug,
                $path,
            );
        } catch (Throwable $exception) {
            $feedImport->update([
                'status' => FeedImport::STATUS_FAILED,
                'errors' => [[
                    'code' => 'feed_read_failed',
                    'message' => $exception->getMessage(),
                ]],
            ]);

            return $feedImport->refresh();
        }

        $maxRecords = (int) config('feeds.admin.max_records', 5000);

        $sourceRecordCount = collect($report->items)
            ->filter(fn ($item): bool => $item->record !== null)
            ->count();

        if ($sourceRecordCount > $maxRecords) {
            $feedImport->update([
                'status' => FeedImport::STATUS_FAILED,
                'total_count' => $sourceRecordCount,
                'errors' => [[
                    'code' => 'record_limit_exceeded',
                    'message' => "The feed contains more than {$maxRecords} records.",
                ]],
            ]);

            return $feedImport->refresh();
        }

        DB::transaction(function () use ($feedImport, $report): void {
            $feedImport->items()->delete();

            foreach ($report->items as $item) {
                $feedImport->items()->create($item->toArray());
            }

            $counts = $report->counts();
            $feedImport->update([
                'status' => $report->issues === []
                    ? FeedImport::STATUS_READY
                    : FeedImport::STATUS_FAILED,
                'total_count' => count($report->items),
                'ready_count' => collect([
                    'created',
                    'updated',
                    'unchanged',
                    'unavailable',
                ])->sum(fn (string $outcome): int => $counts[$outcome] ?? 0),
                'review_count' => $counts['manual_review'] ?? 0,
                'invalid_count' => $counts['invalid'] ?? 0,
                'errors' => $report->issues === []
                    ? null
                    : array_map(
                        fn ($issue): array => $issue->toArray(),
                        $report->issues,
                    ),
            ]);
        });

        return $feedImport->refresh();
    }

    public function resolve(
        FeedImportItem $item,
        string $resolution,
        ?int $variantId,
        ?int $userId,
        array $newVariant = [],
    ): FeedImportItem {
        if ($item->feedImport->status !== FeedImport::STATUS_READY) {
            throw new LogicException('Only ready imports can be reviewed.');
        }

        if ($item->outcome !== 'manual_review') {
            throw new LogicException('Only review items can be resolved.');
        }

        if (! in_array($resolution, [
            FeedImportItem::RESOLUTION_ATTACH,
            FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT,
            FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT,
            FeedImportItem::RESOLUTION_IGNORE,
        ], true)) {
            throw new LogicException('Unknown review resolution.');
        }

        $usesVariant = in_array($resolution, [
            FeedImportItem::RESOLUTION_ATTACH,
            FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT,
        ], true);

        if ($resolution === FeedImportItem::RESOLUTION_ATTACH && ! $item->canAttachToVariant()) {
            throw new LogicException('This identity conflict cannot be attached safely.');
        }

        if (
            $resolution === FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT
            && ! $item->canCreateColourVariant()
        ) {
            throw new LogicException('This identity conflict cannot create a variant safely.');
        }

        if (
            $resolution === FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT
            && ! $item->canCreateShoeVariant()
        ) {
            throw new LogicException('This identity conflict cannot create a shoe safely.');
        }

        if ($usesVariant && $variantId === null) {
            throw new LogicException('Select a shoe variant.');
        }

        $variant = $usesVariant
            ? ShoeVariant::query()->findOrFail($variantId)
            : null;

        if ($variant !== null && ! $variant->active) {
            throw new LogicException('Select an active shoe variant.');
        }

        if ($resolution === FeedImportItem::RESOLUTION_ATTACH) {
            $listingExists = $variant->retailerListings()
                ->where('retailer_id', $item->feedImport->retailer_id)
                ->exists();

            if ($listingExists) {
                throw new LogicException('This retailer already has an offer for the selected variant.');
            }
        }

        $newVariantAttributes = $resolution === FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT
            ? $this->validateNewVariant($item, $variant, $newVariant)
            : ($resolution === FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT
                ? $this->validateNewShoeVariant($item, $newVariant)
                : $this->emptyNewCatalogueAttributes());

        $item->update([
            'resolution' => $resolution,
            'selected_variant_id' => $usesVariant
                ? $variantId
                : null,
            ...$newVariantAttributes,
            'resolved_by' => $userId,
            'resolved_at' => now(),
        ]);

        return $item->refresh();
    }

    public function apply(FeedImport $feedImport): FeedImport
    {
        $feedImport->refresh();

        if (! $feedImport->canApply()) {
            throw new LogicException('The import is not ready to apply.');
        }

        DB::transaction(function () use ($feedImport): void {
            $feedImport->items()
                ->orderBy('id')
                ->each(function (FeedImportItem $item) use ($feedImport): void {
                    if (in_array($item->outcome, ['invalid', 'missing'], true)) {
                        return;
                    }

                    if (
                        $item->outcome === 'manual_review'
                        && $item->resolution === FeedImportItem::RESOLUTION_IGNORE
                    ) {
                        return;
                    }

                    $record = new FeedRecord(
                        $item->source_record,
                        $item->normalized_payload,
                        $item->raw_payload,
                    );
                    $listing = $item->matched_listing_id === null
                        ? null
                        : RetailerListing::query()->findOrFail($item->matched_listing_id);
                    if (
                        $item->resolution
                        === FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT
                    ) {
                        $variant = $this->createColourVariant($item);
                    } elseif (
                        $item->resolution
                        === FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT
                    ) {
                        $variant = $this->createShoeVariant($item);
                    } else {
                        $variantId = $item->selected_variant_id ?? $item->matched_variant_id;
                        $variant = $listing === null && $variantId !== null
                            ? ShoeVariant::query()->findOrFail($variantId)
                            : null;
                    }

                    $this->synchronizer->sync(
                        $feedImport->retailer,
                        $record,
                        $listing,
                        $variant,
                    );
                });

            $feedImport->update([
                'status' => FeedImport::STATUS_APPLIED,
                'applied_at' => now(),
            ]);
        });

        return $feedImport->refresh();
    }

    private function validateNewVariant(
        FeedImportItem $item,
        ShoeVariant $baseVariant,
        array $data,
    ): array {
        $attributes = $this->validateNewColour($item, $data);
        $variantCode = $attributes['new_manufacturer_variant_code'];
        $selectedColourId = $attributes['selected_colour_id'];

        if ($selectedColourId !== null) {
            $colourExists = $baseVariant->shoe->variants()
                ->where('colour_id', $selectedColourId)
                ->exists();
            $pendingColourExists = $item->feedImport->items()
                ->whereKeyNot($item->getKey())
                ->where('resolution', FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT)
                ->where('selected_colour_id', $selectedColourId)
                ->whereHas(
                    'selectedVariant',
                    fn ($query) => $query->where('shoe_id', $baseVariant->shoe_id),
                )
                ->exists();

            if ($colourExists || $pendingColourExists) {
                throw new LogicException('Šim apavu modelim šāds krāsas variants jau pastāv.');
            }
        } elseif ($attributes['new_colour_code'] !== null) {
            $pendingColourExists = $item->feedImport->items()
                ->whereKeyNot($item->getKey())
                ->where('resolution', FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT)
                ->where('new_colour_code', $attributes['new_colour_code'])
                ->whereHas(
                    'selectedVariant',
                    fn ($query) => $query->where('shoe_id', $baseVariant->shoe_id),
                )
                ->exists();

            if ($pendingColourExists) {
                throw new LogicException('Šim apavu modelim šāds krāsas variants jau pastāv.');
            }
        }

        if ($variantCode !== null) {
            $variantCodeExists = $baseVariant->shoe->variants()
                ->where('manufacturer_variant_code', $variantCode)
                ->exists();
            $pendingVariantCodeExists = $item->feedImport->items()
                ->whereKeyNot($item->getKey())
                ->where('resolution', FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT)
                ->where('new_manufacturer_variant_code', $variantCode)
                ->whereHas(
                    'selectedVariant',
                    fn ($query) => $query->where('shoe_id', $baseVariant->shoe_id),
                )
                ->exists();

            if ($variantCodeExists || $pendingVariantCodeExists) {
                throw new LogicException('Šim apavu modelim ražotāja varianta kods jau tiek izmantots.');
            }
        }

        return [
            ...$attributes,
            ...$this->emptyNewShoeAttributes(),
        ];
    }

    private function validateNewShoeVariant(
        FeedImportItem $item,
        array $data,
    ): array {
        $brand = Brand::query()->findOrFail($data['new_shoe_brand_id'] ?? null);
        $category = Category::query()->findOrFail($data['new_shoe_category_id'] ?? null);
        $name = trim((string) ($data['new_shoe_name'] ?? ''));
        $slug = trim((string) ($data['new_shoe_slug'] ?? ''));
        $styleCode = filled($data['new_shoe_style_code'] ?? null)
            ? trim((string) $data['new_shoe_style_code'])
            : null;
        $audience = (string) ($data['new_shoe_audience'] ?? '');

        if (! $brand->active || ! $category->active) {
            throw new LogicException('Izvēlies aktīvu zīmolu un kategoriju.');
        }

        if ($name === '') {
            throw new LogicException('Norādi apavu modeļa nosaukumu.');
        }

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new LogicException('Adresē izmanto mazos burtus, ciparus un defises.');
        }

        if (
            mb_strlen($name) > 255
            || mb_strlen($slug) > 255
            || ($styleCode !== null && mb_strlen($styleCode) > 100)
        ) {
            throw new LogicException('Jaunā apavu modeļa dati ir pārāk gari.');
        }

        if (Audience::tryFrom($audience) === null) {
            throw new LogicException('Izvēlies apavu auditoriju.');
        }

        $slugExists = Shoe::query()->where('slug', $slug)->exists();
        $pendingSlugExists = $item->feedImport->items()
            ->whereKeyNot($item->getKey())
            ->where('resolution', FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT)
            ->where('new_shoe_slug', $slug)
            ->exists();

        if ($slugExists || $pendingSlugExists) {
            throw new LogicException('Šāda apavu modeļa adrese jau tiek izmantota.');
        }

        return [
            ...$this->validateNewColour($item, $data),
            'new_shoe_brand_id' => $brand->getKey(),
            'new_shoe_category_id' => $category->getKey(),
            'new_shoe_name' => $name,
            'new_shoe_slug' => $slug,
            'new_shoe_style_code' => $styleCode,
            'new_shoe_audience' => $audience,
        ];
    }

    private function validateNewColour(
        FeedImportItem $item,
        array $data,
    ): array {
        $selectedColourId = filled($data['selected_colour_id'] ?? null)
            ? (int) $data['selected_colour_id']
            : null;
        $colourCode = trim((string) ($data['new_colour_code'] ?? ''));
        $name = trim((string) ($data['new_colour_name'] ?? ''));
        $filterColourIds = collect($data['new_filter_colour_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
        $variantCode = filled($data['new_manufacturer_variant_code'] ?? null)
            ? trim((string) $data['new_manufacturer_variant_code'])
            : null;

        if ($selectedColourId !== null) {
            $colour = Colour::query()->find($selectedColourId);

            if ($colour === null || ! $colour->active) {
                throw new LogicException('Izvēlies aktīvu krāsu.');
            }

            if ($variantCode !== null && mb_strlen($variantCode) > 100) {
                throw new LogicException('Ražotāja varianta kods ir pārāk garš.');
            }

            return [
                'selected_colour_id' => $colour->getKey(),
                'new_colour_code' => null,
                'new_colour_name' => null,
                'new_filter_colour_ids' => null,
                'new_manufacturer_variant_code' => $variantCode,
            ];
        }

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $colourCode)) {
            throw new LogicException('Krāsas kodā izmanto mazos burtus, ciparus un defises.');
        }

        if ($name === '') {
            throw new LogicException('Norādi krāsas nosaukumu.');
        }

        if ($filterColourIds === []) {
            throw new LogicException('Izvēlies vismaz vienu filtra krāsu.');
        }

        $validFilterColourIds = FilterColour::query()
            ->where('active', true)
            ->whereKey($filterColourIds)
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        if ($validFilterColourIds !== $filterColourIds) {
            throw new LogicException('Izvēlies tikai aktīvas filtra krāsas.');
        }

        if (
            mb_strlen($colourCode) > 64
            || mb_strlen($name) > 255
            || ($variantCode !== null && mb_strlen($variantCode) > 100)
        ) {
            throw new LogicException('Jaunā varianta dati ir pārāk gari.');
        }

        $colourExists = Colour::query()
            ->where('code', $colourCode)
            ->exists();
        $pendingColour = $item->feedImport->items()
            ->whereKeyNot($item->getKey())
            ->whereIn('resolution', [
                FeedImportItem::RESOLUTION_CREATE_COLOUR_VARIANT,
                FeedImportItem::RESOLUTION_CREATE_SHOE_VARIANT,
            ])
            ->where('new_colour_code', $colourCode)
            ->first();

        if ($colourExists) {
            throw new LogicException('Šāds krāsas kods jau tiek izmantots.');
        }

        if (
            $pendingColour !== null
            && $pendingColour->new_colour_name !== $name
        ) {
            throw new LogicException('Gaidošajai krāsai ar šo kodu ir cits nosaukums.');
        }

        if (
            $pendingColour !== null
            && collect($pendingColour->new_filter_colour_ids ?? [])
                ->map(fn (mixed $id): int => (int) $id)
                ->sort()
                ->values()
                ->all() !== $filterColourIds
        ) {
            throw new LogicException('Gaidošajai krāsai ar šo kodu ir citas filtra krāsas.');
        }

        return [
            'selected_colour_id' => null,
            'new_colour_code' => $colourCode,
            'new_colour_name' => $name,
            'new_filter_colour_ids' => $filterColourIds,
            'new_manufacturer_variant_code' => $variantCode,
        ];
    }

    private function createColourVariant(FeedImportItem $item): ShoeVariant
    {
        $baseVariant = ShoeVariant::query()->findOrFail($item->selected_variant_id);
        $colour = $this->createColour($item);
        $variant = $baseVariant->shoe->variants()->create([
            'colour_id' => $colour->getKey(),
            'manufacturer_variant_code' => $item->new_manufacturer_variant_code,
            'active' => true,
        ]);

        $item->update(['created_variant_id' => $variant->getKey()]);

        return $variant;
    }

    private function createShoeVariant(FeedImportItem $item): ShoeVariant
    {
        $shoe = Shoe::query()->create([
            'brand_id' => $item->new_shoe_brand_id,
            'category_id' => $item->new_shoe_category_id,
            'name' => $item->new_shoe_name,
            'slug' => $item->new_shoe_slug,
            'manufacturer_style_code' => $item->new_shoe_style_code,
            'audience' => Audience::from($item->new_shoe_audience),
            'description_lv' => null,
            'description_en' => null,
            'active' => true,
        ]);
        $colour = $this->createColour($item);
        $variant = $shoe->variants()->create([
            'colour_id' => $colour->getKey(),
            'manufacturer_variant_code' => $item->new_manufacturer_variant_code,
            'active' => true,
        ]);

        $item->update([
            'created_shoe_id' => $shoe->getKey(),
            'created_variant_id' => $variant->getKey(),
        ]);

        return $variant;
    }

    private function createColour(FeedImportItem $item): Colour
    {
        if ($item->selected_colour_id !== null) {
            return Colour::query()->findOrFail($item->selected_colour_id);
        }

        $colour = Colour::query()->firstOrCreate([
            'code' => $item->new_colour_code,
        ], [
            'name' => $item->new_colour_name,
            'sort_order' => 0,
            'active' => true,
        ]);

        if ($colour->name !== $item->new_colour_name) {
            throw new LogicException('Krāsas kodam un nosaukumam ir pretrunīgi dati.');
        }

        $colour->filterColours()->syncWithoutDetaching(
            $item->new_filter_colour_ids ?? [],
        );

        return $colour;
    }

    private function emptyNewCatalogueAttributes(): array
    {
        return [
            'selected_colour_id' => null,
            'new_colour_code' => null,
            'new_colour_name' => null,
            'new_filter_colour_ids' => null,
            'new_manufacturer_variant_code' => null,
            ...$this->emptyNewShoeAttributes(),
        ];
    }

    private function emptyNewShoeAttributes(): array
    {
        return [
            'new_shoe_brand_id' => null,
            'new_shoe_category_id' => null,
            'new_shoe_name' => null,
            'new_shoe_slug' => null,
            'new_shoe_style_code' => null,
            'new_shoe_audience' => null,
        ];
    }
}
