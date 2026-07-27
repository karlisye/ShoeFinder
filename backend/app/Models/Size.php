<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['eu_size', 'label', 'sort_order', 'active'])]
class Size extends Model
{
    public function listingSizes(): HasMany
    {
        return $this->hasMany(RetailerListingSize::class);
    }

    protected function casts(): array
    {
        return [
            'eu_size' => 'decimal:1',
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }
}
