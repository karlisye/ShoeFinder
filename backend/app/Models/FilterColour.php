<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'code',
    'name_lv',
    'name_en',
    'sort_order',
    'active',
])]
class FilterColour extends Model
{
    public function colourways(): BelongsToMany
    {
        return $this->belongsToMany(
            Colour::class,
            'colour_filter_colour',
        )->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }
}
