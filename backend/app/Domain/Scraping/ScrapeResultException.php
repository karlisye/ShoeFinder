<?php

namespace App\Domain\Scraping;

use RuntimeException;

class ScrapeResultException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly bool $retryable = false,
    ) {
        parent::__construct($message);
    }
}
