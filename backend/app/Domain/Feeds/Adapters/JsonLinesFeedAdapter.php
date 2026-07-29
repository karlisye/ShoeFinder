<?php

namespace App\Domain\Feeds\Adapters;

use App\Domain\Feeds\Contracts\FeedAdapter;
use App\Domain\Feeds\Data\FeedIssue;
use App\Domain\Feeds\Data\FeedReadResult;
use App\Domain\Feeds\Data\FeedRecord;
use JsonException;

class JsonLinesFeedAdapter implements FeedAdapter
{
    public function read(string $path): FeedReadResult
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return new FeedReadResult([], [
                new FeedIssue(null, null, 'file_unreadable', 'Feed file could not be opened.'),
            ]);
        }

        $records = [];
        $issues = [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            try {
                $raw = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $issues[] = new FeedIssue(
                    $index + 1,
                    null,
                    'syntax_error',
                    'JSON line is malformed.',
                );

                continue;
            }

            if (! is_array($raw)) {
                $issues[] = new FeedIssue(
                    $index + 1,
                    null,
                    'syntax_error',
                    'JSON line must contain an object.',
                );

                continue;
            }

            $records[] = new FeedRecord($index + 1, $raw, $raw);
        }

        return new FeedReadResult($records, $issues);
    }
}
