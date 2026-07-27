<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'shoe_id',
    'colour_id',
    'manufacturer_variant_code',
    'active',
])]
class ShoeVariant extends Model
{
    public function shoe(): BelongsTo
    {
        return $this->belongsTo(Shoe::class);
    }

    public function colour(): BelongsTo
    {
        return $this->belongsTo(Colour::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ShoeImage::class);
    }

    public function retailerListings(): HasMany
    {
        return $this->hasMany(RetailerListing::class);
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
