<?php

namespace App\Domain\Feeds\Data;

class FeedRecord
{
    public function __construct(
        public readonly int $sourceRecord,
        public readonly array $data,
        public readonly array $raw,
    ) {}

    public function identity(): string
    {
        return $this->data['retailer_external_id']
            ?? $this->data['retailer_sku']
            ?? "record-{$this->sourceRecord}";
    }
}
