<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'scrape_run_id',
    'retailer_listing_id',
    'position',
    'status',
    'product_url',
    'listing_label',
    'baseline',
    'result_payload',
    'changes',
    'error',
    'observed_at',
])]
class ScrapeRunItem extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CHANGED = 'changed';

    public const STATUS_UNCHANGED = 'unchanged';

    public const STATUS_UNAVAILABLE = 'unavailable';

    public const STATUS_FAILED = 'failed';

    public const STATUS_APPLIED = 'applied';

    public function scrapeRun(): BelongsTo
    {
        return $this->belongsTo(ScrapeRun::class);
    }

    public function retailerListing(): BelongsTo
    {
        return $this->belongsTo(RetailerListing::class);
    }

    public function wasSuccessful(): bool
    {
        return in_array($this->status, [
            self::STATUS_CHANGED,
            self::STATUS_UNCHANGED,
            self::STATUS_UNAVAILABLE,
            self::STATUS_APPLIED,
        ], true);
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'baseline' => 'array',
            'result_payload' => 'array',
            'changes' => 'array',
            'error' => 'array',
            'observed_at' => 'immutable_datetime',
        ];
    }
}
