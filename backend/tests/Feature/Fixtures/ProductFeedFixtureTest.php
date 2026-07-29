<?php

namespace Tests\Feature\Fixtures;

use DOMDocument;
use Tests\TestCase;

class ProductFeedFixtureTest extends TestCase
{
    private string $fixtureRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureRoot = base_path('tests/Fixtures/ProductFeeds');
    }

    public function test_clean_feeds_contain_twenty_five_records_each(): void
    {
        $expected = $this->fixtureJson('expected/initial-normalized.json');

        $this->assertSame(
            $expected['urban-step'],
            $this->fixtureJson('clean/urban-step.json')['products'],
        );

        $jsonLines = array_map(
            fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
            file($this->path('clean/sneaker-point.jsonl'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES),
        );
        $this->assertSame($expected['sneaker-point'], $jsonLines);

        $csv = fopen($this->path('clean/sole-market.csv'), 'rb');
        $header = fgetcsv($csv, separator: ';', escape: '');
        $csvRows = [];
        while (($row = fgetcsv($csv, separator: ';', escape: '')) !== false) {
            $csvRows[] = $row;
        }
        fclose($csv);

        $this->assertCount(22, $header);
        $this->assertCount(25, $csvRows);

        $xml = new DOMDocument;
        $this->assertTrue($xml->load($this->path('clean/apavu-nams.xml')));
        $this->assertTrue($xml->schemaValidate($this->path('schemas/xml-feed.xsd')));
        $this->assertSame(
            25,
            $xml->getElementsByTagNameNS(
                'https://feeds.shoefinder.example/v1',
                'product',
            )->length,
        );

        foreach ($expected as $retailer => $records) {
            $this->assertCount(25, $records, $retailer);
        }
    }

    public function test_normalized_records_use_stable_safe_values(): void
    {
        $expected = $this->fixtureJson('expected/initial-normalized.json');

        foreach ($expected as $records) {
            foreach ($records as $record) {
                $this->assertMatchesRegularExpression(
                    '/^(?:0|[1-9][0-9]*)\.[0-9]{2}$/',
                    $record['current_price'],
                );
                $this->assertSame('EUR', $record['currency']);
                $this->assertSame(
                    'example',
                    pathinfo((string) parse_url($record['product_url'], PHP_URL_HOST), PATHINFO_EXTENSION),
                );

                if ($record['gtin'] !== null) {
                    $this->assertTrue($this->hasValidGtinCheckDigit($record['gtin']));
                }

                foreach ($record['sizes'] as $size) {
                    $this->assertIsString($size['eu_size']);
                    $this->assertIsBool($size['in_stock']);
                }
            }
        }
    }

    public function test_update_expectations_cover_all_reconciliation_outcomes(): void
    {
        $expected = $this->fixtureJson('expected/update-normalized.json');
        $outcomes = array_count_values(array_column($expected['outcomes'], 'outcome'));

        foreach ([
            'created',
            'updated',
            'unchanged',
            'unavailable',
            'manual_review',
            'missing',
        ] as $outcome) {
            $this->assertArrayHasKey($outcome, $outcomes);
            $this->assertGreaterThan(0, $outcomes[$outcome]);
        }

        $this->assertSame('report_only', $expected['missing_policy']);

        foreach ($expected['records'] as $records) {
            $this->assertCount(25, $records);
        }

        $this->assertSame(
            $expected['records']['urban-step'],
            $this->fixtureJson('updates/urban-step.json')['products'],
        );

        $jsonLines = array_map(
            fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
            file($this->path('updates/sneaker-point.jsonl'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES),
        );
        $this->assertSame($expected['records']['sneaker-point'], $jsonLines);

        $csv = fopen($this->path('updates/sole-market.csv'), 'rb');
        fgetcsv($csv, separator: ';', escape: '');
        $csvRows = [];
        while (($row = fgetcsv($csv, separator: ';', escape: '')) !== false) {
            $csvRows[] = $row;
        }
        fclose($csv);
        $this->assertCount(25, $csvRows);

        $xml = new DOMDocument;
        $this->assertTrue($xml->load($this->path('updates/apavu-nams.xml')));
        $this->assertTrue($xml->schemaValidate($this->path('schemas/xml-feed.xsd')));
        $this->assertSame(
            25,
            $xml->getElementsByTagNameNS(
                'https://feeds.shoefinder.example/v1',
                'product',
            )->length,
        );
    }

    public function test_invalid_fixtures_and_expected_errors_are_separate(): void
    {
        foreach ([
            'invalid/sole-market-invalid.csv',
            'invalid/urban-step-invalid.json',
            'invalid/sneaker-point-invalid.jsonl',
            'invalid/apavu-nams-invalid.xml',
        ] as $path) {
            $this->assertFileExists($this->path($path));
        }

        $errors = $this->fixtureJson('expected/validation-errors.json');
        $codes = array_unique(array_column($errors, 'code'));

        foreach ([
            'syntax_error',
            'identity_missing',
            'identity_duplicate',
            'identity_conflict',
            'gtin_invalid',
            'money_negative',
            'money_invalid',
            'currency_unsupported',
            'url_invalid',
            'delivery_range_invalid',
            'date_invalid',
            'field_unknown',
            'size_duplicate',
            'boolean_invalid',
        ] as $code) {
            $this->assertContains($code, $codes);
        }
    }

    private function path(string $path): string
    {
        return "{$this->fixtureRoot}/{$path}";
    }

    private function fixtureJson(string $path): array
    {
        return json_decode(
            file_get_contents($this->path($path)),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    private function hasValidGtinCheckDigit(string $gtin): bool
    {
        $digits = array_map('intval', str_split($gtin));
        $checkDigit = array_pop($digits);
        $sum = 0;
        $weight = count($digits) % 2 === 0 ? 1 : 3;

        foreach ($digits as $digit) {
            $sum += $digit * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }

        return $checkDigit === (10 - ($sum % 10)) % 10;
    }
}
