<?php

namespace App\Domain\Feeds\Contracts;

use App\Domain\Feeds\Data\FeedReadResult;

interface FeedAdapter
{
    public function read(string $path): FeedReadResult;
}
