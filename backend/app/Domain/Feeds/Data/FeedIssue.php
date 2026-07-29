<?php

namespace App\Domain\Feeds\Data;

class FeedIssue
{
    public function __construct(
        public readonly ?int $record,
        public readonly ?string $field,
        public readonly string $code,
        public readonly string $message,
    ) {}

    public function toArray(): array
    {
        return [
            'record' => $this->record,
            'field' => $this->field,
            'code' => $this->code,
            'message' => $this->message,
        ];
    }
}
