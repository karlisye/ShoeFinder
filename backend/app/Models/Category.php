<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug',
    'name_lv',
    'name_en',
    'description_lv',
    'description_en',
    'sort_order',
    'active',
])]
class Category extends Model
{
    public function shoes(): HasMany
    {
        return $this->hasMany(Shoe::class);
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }
}
