<?php

namespace App\Models;

use App\Enums\SiteLocale;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['retailer_listing_id', 'locale', 'referrer_path', 'clicked_at'])]
#[WithoutTimestamps]
class OutboundClick extends Model
{
    public function retailerListing(): BelongsTo
    {
        return $this->belongsTo(RetailerListing::class);
    }

    protected function casts(): array
    {
        return [
            'locale' => SiteLocale::class,
            'clicked_at' => 'immutable_datetime',
        ];
    }
}
