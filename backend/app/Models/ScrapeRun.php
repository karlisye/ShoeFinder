<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'retailer_id',
    'status',
    'total_count',
    'successful_count',
    'failed_count',
    'changed_count',
    'cancellation_reason',
    'errors',
    'started_at',
    'finished_at',
    'cancelled_at',
    'applied_at',
])]
class ScrapeRun extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_SCRAPING = 'scraping';

    public const STATUS_READY = 'ready';

    public const STATUS_APPLY_QUEUED = 'apply_queued';

    public const STATUS_APPLYING = 'applying';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_FAILED = 'failed';

    public const STATUS_STALE = 'stale';

    public const STATUS_CANCELLED = 'cancelled';

    public const CANCELLATION_MANUAL = 'manual';

    public const CANCELLATION_SUPERSEDED = 'superseded';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ScrapeRunItem::class)->orderBy('position');
    }

    public function canApply(): bool
    {
        return $this->status === self::STATUS_READY
            && $this->successful_count > 0;
    }

    public function canCancel(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    protected function casts(): array
    {
        return [
            'total_count' => 'integer',
            'successful_count' => 'integer',
            'failed_count' => 'integer',
            'changed_count' => 'integer',
            'errors' => 'array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'applied_at' => 'immutable_datetime',
        ];
    }
}
