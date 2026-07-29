<?php

namespace App\Domain\Feeds\Adapters;

use App\Domain\Feeds\Contracts\FeedAdapter;
use App\Domain\Feeds\Data\FeedIssue;
use App\Domain\Feeds\Data\FeedReadResult;
use App\Domain\Feeds\Data\FeedRecord;
use JsonException;

class JsonFeedAdapter implements FeedAdapter
{
    public function read(string $path): FeedReadResult
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            return new FeedReadResult([], [
                new FeedIssue(null, null, 'file_unreadable', 'Feed file could not be opened.'),
            ]);
        }

        try {
            $payload = json_decode(
                $contents,
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return new FeedReadResult([], [
                new FeedIssue(null, null, 'syntax_error', 'JSON feed is malformed.'),
            ]);
        }

        if (! is_array($payload) || ! is_array($payload['products'] ?? null)) {
            return new FeedReadResult([], [
                new FeedIssue(null, 'products', 'required', 'JSON feed has no product array.'),
            ]);
        }

        $records = [];

        foreach ($payload['products'] as $index => $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $records[] = new FeedRecord($index + 1, $raw, $raw);
        }

        return new FeedReadResult($records);
    }
}
