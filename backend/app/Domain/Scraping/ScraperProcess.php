<?php

namespace App\Domain\Scraping;

use App\Models\ScrapeRunItem;
use Illuminate\Support\Facades\Process;
use JsonException;

class ScraperProcess
{
    /** @return array<string, mixed> */
    public function scrape(ScrapeRunItem $item): array
    {
        $retailer = $item->retailerListing?->retailer;
        $adapter = $retailer === null
            ? null
            : config("scraper.retailers.{$retailer->slug}.adapter");

        if (! is_string($adapter)) {
            return $this->failure('adapter_missing', 'The listing has no supported scraper adapter.');
        }

        $input = json_encode([
            'request_id' => (string) $item->getKey(),
            'adapter' => $adapter,
            'url' => $item->product_url,
            'timeout_seconds' => (int) config('scraper.timeout_seconds', 30),
            'user_agent' => (string) config('scraper.user_agent'),
        ], JSON_THROW_ON_ERROR);

        $result = Process::path(base_path('scraper'))
            ->timeout((int) config('scraper.timeout_seconds', 30) + 5)
            ->input($input)
            ->run([
                (string) config('scraper.python_binary', 'python3'),
                '-m',
                'shoe_scraper',
            ]);

        if ($result->failed()) {
            return $this->failure('process_failed', 'The scraper process could not be completed.');
        }

        try {
            $payload = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->failure('invalid_output', 'The scraper returned invalid output.');
        }

        return is_array($payload)
            ? $payload
            : $this->failure('invalid_output', 'The scraper returned invalid output.');
    }

    /** @return array<string, mixed> */
    private function failure(string $code, string $message): array
    {
        return [
            'schema_version' => 1,
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'retryable' => false,
            ],
        ];
    }
}
