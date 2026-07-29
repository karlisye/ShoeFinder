<?php

namespace App\Domain\Feeds\Data;

use Illuminate\Support\Collection;

class FeedImportReport
{
    /**
     * @param  array<int, FeedImportItem>  $items
     * @param  array<int, FeedIssue>  $issues
     */
    public function __construct(
        public readonly string $retailer,
        public readonly string $path,
        public readonly bool $applied,
        public readonly array $items,
        public readonly array $issues,
    ) {}

    public function counts(): array
    {
        return collect($this->items)
            ->countBy(fn (FeedImportItem $item): string => $item->outcome)
            ->sortKeys()
            ->all();
    }

    /**
     * @return Collection<int, FeedImportItem>
     */
    public function itemsFor(string $outcome): Collection
    {
        return collect($this->items)
            ->filter(fn (FeedImportItem $item): bool => $item->outcome === $outcome)
            ->values();
    }
}
