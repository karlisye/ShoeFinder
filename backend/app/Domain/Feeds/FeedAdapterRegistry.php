<?php

namespace App\Domain\Feeds;

use App\Domain\Feeds\Adapters\CsvFeedAdapter;
use App\Domain\Feeds\Adapters\JsonFeedAdapter;
use App\Domain\Feeds\Adapters\JsonLinesFeedAdapter;
use App\Domain\Feeds\Adapters\XmlFeedAdapter;
use App\Domain\Feeds\Contracts\FeedAdapter;
use InvalidArgumentException;

class FeedAdapterRegistry
{
    public function forRetailer(string $retailer): FeedAdapter
    {
        $format = config("feeds.retailers.{$retailer}.format");

        return match ($format) {
            'csv' => app(CsvFeedAdapter::class),
            'json' => app(JsonFeedAdapter::class),
            'jsonl' => app(JsonLinesFeedAdapter::class),
            'xml' => app(XmlFeedAdapter::class),
            default => throw new InvalidArgumentException("Unknown feed retailer: {$retailer}"),
        };
    }
}
