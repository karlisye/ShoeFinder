<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['retailer_listing_id', 'size_id', 'in_stock', 'price'])]
class RetailerListingSize extends Model
{
    public function retailerListing(): BelongsTo
    {
        return $this->belongsTo(RetailerListing::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function effectivePrice(): string
    {
        if ($this->price !== null) {
            return $this->price;
        }

        $listing = $this->retailerListing;

        if ($listing === null) {
            throw new LogicException('A listing size requires a retailer listing to resolve its price.');
        }

        return $listing->current_price;
    }

    protected function casts(): array
    {
        return [
            'in_stock' => 'boolean',
            'price' => 'decimal:2',
        ];
    }
}
