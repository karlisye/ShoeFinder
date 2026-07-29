<?php

namespace App\Domain\Feeds\Data;

class FeedReadResult
{
    /**
     * @param  array<int, FeedRecord>  $records
     * @param  array<int, FeedIssue>  $issues
     */
    public function __construct(
        public readonly array $records,
        public readonly array $issues = [],
    ) {}
}
