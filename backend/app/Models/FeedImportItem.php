<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'feed_import_id',
    'source_record',
    'identity',
    'outcome',
    'reason',
    'normalized_payload',
    'raw_payload',
    'issues',
    'matched_listing_id',
    'matched_variant_id',
    'selected_variant_id',
    'selected_colour_id',
    'new_colour_code',
    'new_colour_name',
    'new_manufacturer_variant_code',
    'created_variant_id',
    'new_shoe_brand_id',
    'new_shoe_category_id',
    'new_shoe_name',
    'new_shoe_slug',
    'new_shoe_style_code',
    'new_shoe_audience',
    'created_shoe_id',
    'resolution',
    'resolved_by',
    'resolved_at',
])]
class FeedImportItem extends Model
{
    public const RESOLUTION_ATTACH = 'attach_existing_variant';

    public const RESOLUTION_IGNORE = 'ignore';

    public const RESOLUTION_CREATE_COLOUR_VARIANT = 'create_colour_variant';

    public const RESOLUTION_CREATE_SHOE_VARIANT = 'create_shoe_variant';

    public function feedImport(): BelongsTo
    {
        return $this->belongsTo(FeedImport::class);
    }

    public function matchedListing(): BelongsTo
    {
        return $this->belongsTo(RetailerListing::class, 'matched_listing_id');
    }

    public function matchedVariant(): BelongsTo
    {
        return $this->belongsTo(ShoeVariant::class, 'matched_variant_id');
    }

    public function selectedVariant(): BelongsTo
    {
        return $this->belongsTo(ShoeVariant::class, 'selected_variant_id');
    }

    public function selectedColour(): BelongsTo
    {
        return $this->belongsTo(Colour::class, 'selected_colour_id');
    }

    public function createdVariant(): BelongsTo
    {
        return $this->belongsTo(ShoeVariant::class, 'created_variant_id');
    }

    public function newShoeBrand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'new_shoe_brand_id');
    }

    public function newShoeCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'new_shoe_category_id');
    }

    public function createdShoe(): BelongsTo
    {
        return $this->belongsTo(Shoe::class, 'created_shoe_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function canAttachToVariant(): bool
    {
        return $this->outcome === 'manual_review'
            && in_array($this->reason, [
                'no_strong_match',
                'ambiguous_strong_match',
            ], true);
    }

    public function canCreateColourVariant(): bool
    {
        return $this->canAttachToVariant();
    }

    public function canCreateShoeVariant(): bool
    {
        return $this->canAttachToVariant();
    }

    protected function casts(): array
    {
        return [
            'normalized_payload' => 'array',
            'raw_payload' => 'array',
            'issues' => 'array',
            'resolved_at' => 'immutable_datetime',
        ];
    }
}
