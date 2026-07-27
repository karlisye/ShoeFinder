<?php

namespace App\Models;

use App\Enums\ImageSourceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'shoe_variant_id',
    'source_type',
    'path',
    'external_url',
    'alt_text_lv',
    'alt_text_en',
    'sort_order',
    'is_primary',
])]
class ShoeImage extends Model
{
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ShoeVariant::class, 'shoe_variant_id');
    }

    protected function casts(): array
    {
        return [
            'source_type' => ImageSourceType::class,
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
        ];
    }
}
