<?php

namespace App\Models;

use App\Enums\ListingSourceType;
use App\Observers\RetailerListingObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'shoe_variant_id',
    'retailer_id',
    'product_url',
    'affiliate_url',
    'retailer_external_id',
    'retailer_sku',
    'gtin',
    'manufacturer_style_code',
    'raw_title',
    'raw_colour',
    'source_type',
    'raw_payload',
    'current_price',
    'original_price',
    'currency',
    'delivery_cost',
    'delivery_min_days',
    'delivery_max_days',
    'delivery_note_lv',
    'delivery_note_en',
    'active',
    'last_checked_at',
])]
#[ObservedBy(RetailerListingObserver::class)]
class RetailerListing extends Model
{
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ShoeVariant::class, 'shoe_variant_id');
    }

    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class);
    }

    public function listingSizes(): HasMany
    {
        return $this->hasMany(RetailerListingSize::class);
    }

    public function priceChanges(): HasMany
    {
        return $this->hasMany(PriceChange::class);
    }

    public function outboundClicks(): HasMany
    {
        return $this->hasMany(OutboundClick::class);
    }

    protected function casts(): array
    {
        return [
            'source_type' => ListingSourceType::class,
            'raw_payload' => 'array',
            'current_price' => 'decimal:2',
            'original_price' => 'decimal:2',
            'delivery_cost' => 'decimal:2',
            'delivery_min_days' => 'integer',
            'delivery_max_days' => 'integer',
            'active' => 'boolean',
            'last_checked_at' => 'immutable_datetime',
        ];
    }
}
