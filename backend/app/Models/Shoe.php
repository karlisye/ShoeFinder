<?php

namespace App\Models;

use App\Enums\Audience;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'brand_id',
    'category_id',
    'name',
    'slug',
    'manufacturer_style_code',
    'audience',
    'description_lv',
    'description_en',
    'active',
])]
class Shoe extends Model
{
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ShoeVariant::class);
    }

    protected function casts(): array
    {
        return [
            'audience' => Audience::class,
            'active' => 'boolean',
        ];
    }
}
