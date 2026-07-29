<?php

namespace App\Domain\Feeds\Data;

class FeedImportItem
{
    public function __construct(
        public readonly ?int $record,
        public readonly string $identity,
        public readonly string $outcome,
        public readonly string $reason,
    ) {}

    public function toArray(): array
    {
        return [
            'record' => $this->record,
            'identity' => $this->identity,
            'outcome' => $this->outcome,
            'reason' => $this->reason,
        ];
    }
}
