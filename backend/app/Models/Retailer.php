<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'website_url', 'logo_path', 'active'])]
class Retailer extends Model
{
    public function listings(): HasMany
    {
        return $this->hasMany(RetailerListing::class);
    }

    public function feedImports(): HasMany
    {
        return $this->hasMany(FeedImport::class);
    }

    public function scrapeRuns(): HasMany
    {
        return $this->hasMany(ScrapeRun::class);
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
