<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['retailer_listing_id', 'price', 'original_price', 'observed_at'])]
#[WithoutTimestamps]
class PriceChange extends Model
{
    public function retailerListing(): BelongsTo
    {
        return $this->belongsTo(RetailerListing::class);
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'original_price' => 'decimal:2',
            'observed_at' => 'immutable_datetime',
        ];
    }
}
