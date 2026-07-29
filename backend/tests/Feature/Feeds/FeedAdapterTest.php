<?php

namespace Tests\Feature\Feeds;

use App\Domain\Feeds\FeedAdapterRegistry;
use App\Domain\Feeds\FeedRecordValidator;
use Database\Seeders\SizeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FeedAdapterTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('cleanFeeds')]
    public function test_clean_feed_normalizes_to_the_shared_contract(
        string $retailer,
        string $file,
    ): void {
        $expected = $this->fixtureJson('expected/initial-normalized.json');
        $result = app(FeedAdapterRegistry::class)
            ->forRetailer($retailer)
            ->read($this->fixturePath($file));

        $this->assertSame([], $result->issues);
        $this->assertSame(
            $expected[$retailer],
            array_map(fn ($record): array => $record->data, $result->records),
        );
    }

    public function test_clean_normalized_records_pass_feed_validation(): void
    {
        $this->seed(SizeSeeder::class);
        $validator = app(FeedRecordValidator::class);

        foreach (self::cleanFeeds() as [$retailer, $file]) {
            $result = app(FeedAdapterRegistry::class)
                ->forRetailer($retailer)
                ->read($this->fixturePath($file));

            foreach ($result->records as $record) {
                $this->assertSame([], $validator->validate($record));
            }
            $this->assertSame([], $validator->duplicateIssues($result->records));
        }
    }

    public function test_streaming_and_xml_adapters_report_syntax_errors(): void
    {
        $jsonLines = app(FeedAdapterRegistry::class)
            ->forRetailer('sneaker-point')
            ->read($this->fixturePath('invalid/sneaker-point-invalid.jsonl'));
        $xml = app(FeedAdapterRegistry::class)
            ->forRetailer('apavu-nams')
            ->read($this->fixturePath('invalid/apavu-nams-invalid.xml'));
        $csv = app(FeedAdapterRegistry::class)
            ->forRetailer('sole-market')
            ->read($this->fixturePath('invalid/sole-market-invalid.csv'));

        $this->assertContains(
            'syntax_error',
            array_column(array_map(fn ($issue): array => $issue->toArray(), $jsonLines->issues), 'code'),
        );
        $this->assertContains(
            'syntax_error',
            array_column(array_map(fn ($issue): array => $issue->toArray(), $xml->issues), 'code'),
        );
        $this->assertContains(
            'syntax_error',
            array_column(array_map(fn ($issue): array => $issue->toArray(), $csv->issues), 'code'),
        );
    }

    public function test_invalid_json_records_produce_stable_validation_codes(): void
    {
        $this->seed(SizeSeeder::class);
        $result = app(FeedAdapterRegistry::class)
            ->forRetailer('urban-step')
            ->read($this->fixturePath('invalid/urban-step-invalid.json'));
        $validator = app(FeedRecordValidator::class);
        $codes = [];

        foreach ($result->records as $record) {
            array_push(
                $codes,
                ...array_map(
                    fn ($issue): string => $issue->code,
                    $validator->validate($record),
                ),
            );
        }

        foreach ([
            'field_unknown',
            'currency_unsupported',
            'original_price_below_current',
            'size_unknown',
        ] as $code) {
            $this->assertContains($code, $codes);
        }

        $duplicateCodes = collect($validator->duplicateIssues($result->records))
            ->flatten()
            ->map(fn ($issue): string => $issue->code)
            ->all();
        $this->assertContains('identity_duplicate', $duplicateCodes);
        $this->assertContains('identity_conflict', $duplicateCodes);
    }

    public static function cleanFeeds(): array
    {
        return [
            ['sole-market', 'clean/sole-market.csv'],
            ['urban-step', 'clean/urban-step.json'],
            ['sneaker-point', 'clean/sneaker-point.jsonl'],
            ['apavu-nams', 'clean/apavu-nams.xml'],
        ];
    }

    private function fixturePath(string $path): string
    {
        return base_path("tests/Fixtures/ProductFeeds/{$path}");
    }

    private function fixtureJson(string $path): array
    {
        return json_decode(
            file_get_contents($this->fixturePath($path)),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
