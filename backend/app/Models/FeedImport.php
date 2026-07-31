<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'retailer_id',
    'user_id',
    'original_filename',
    'stored_path',
    'format',
    'status',
    'total_count',
    'ready_count',
    'review_count',
    'invalid_count',
    'errors',
    'applied_at',
])]
class FeedImport extends Model
{
    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_PREVIEW_QUEUED = 'preview_queued';

    public const STATUS_PREVIEWING = 'previewing';

    public const STATUS_READY = 'ready';

    public const STATUS_APPLY_QUEUED = 'apply_queued';

    public const STATUS_APPLYING = 'applying';

    public const STATUS_FAILED = 'failed';

    public const STATUS_APPLIED = 'applied';

    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeedImportItem::class);
    }

    public function unresolvedReviewCount(): int
    {
        return $this->items()
            ->where('outcome', 'manual_review')
            ->whereNull('resolution')
            ->count();
    }

    public function canApply(): bool
    {
        return $this->status === self::STATUS_READY
            && $this->hasResolvedApplyData();
    }

    public function canRunApply(): bool
    {
        return in_array($this->status, [
            self::STATUS_READY,
            self::STATUS_APPLY_QUEUED,
            self::STATUS_APPLYING,
        ], true)
            && $this->hasResolvedApplyData();
    }

    private function hasResolvedApplyData(): bool
    {
        return $this->invalid_count === 0
            && $this->unresolvedReviewCount() === 0;
    }

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'applied_at' => 'immutable_datetime',
        ];
    }
}
