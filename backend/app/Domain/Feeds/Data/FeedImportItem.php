<?php

namespace App\Domain\Feeds\Data;

class FeedImportItem
{
    public function __construct(
        public readonly ?int $record,
        public readonly string $identity,
        public readonly string $outcome,
        public readonly string $reason,
        public readonly ?array $normalizedPayload = null,
        public readonly ?array $rawPayload = null,
        public readonly array $issues = [],
        public readonly ?int $matchedListingId = null,
        public readonly ?int $matchedVariantId = null,
    ) {}

    public function toArray(): array
    {
        return [
            'source_record' => $this->record,
            'identity' => $this->identity,
            'outcome' => $this->outcome,
            'reason' => $this->reason,
            'normalized_payload' => $this->normalizedPayload,
            'raw_payload' => $this->rawPayload,
            'issues' => $this->issues,
            'matched_listing_id' => $this->matchedListingId,
            'matched_variant_id' => $this->matchedVariantId,
        ];
    }
}
