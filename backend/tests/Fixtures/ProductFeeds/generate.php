<?php

declare(strict_types=1);

const FIXTURE_ROOT = __DIR__;
const OBSERVED_AT = '2026-07-29T09:00:00+03:00';
const UPDATED_AT = '2026-07-30T09:00:00+03:00';

$retailers = [
    [
        'slug' => 'sole-market',
        'name' => 'Sole Market',
        'format' => 'csv',
        'offset' => 0,
        'price_factor' => 1.00,
        'delivery_cost' => '2.99',
        'delivery_days' => [2, 4],
    ],
    [
        'slug' => 'urban-step',
        'name' => 'Urban Step',
        'format' => 'json',
        'offset' => 8,
        'price_factor' => 1.04,
        'delivery_cost' => '0.00',
        'delivery_days' => [3, 5],
    ],
    [
        'slug' => 'sneaker-point',
        'name' => 'Sneaker Point',
        'format' => 'jsonl',
        'offset' => 16,
        'price_factor' => 0.97,
        'delivery_cost' => null,
        'delivery_days' => [2, 6],
    ],
    [
        'slug' => 'apavu-nams',
        'name' => 'Apavu nams',
        'format' => 'xml',
        'offset' => 24,
        'price_factor' => 1.02,
        'delivery_cost' => '3.49',
        'delivery_days' => [1, 3],
    ],
];

$products = [
    ['Nike', 'Air Force 1 \'07', 'CW2288', 'CW2288-111', 'White/White', 119.99],
    ['Nike', 'Dunk Low Retro', 'DD1391', 'DD1391-100', 'White/Black', 119.99],
    ['Nike', 'Air Max 90', 'CN8490', 'CN8490-100', 'White/Wolf Grey', 149.99],
    ['Nike', 'P-6000', 'CD6404', 'CD6404-101', 'White/Metallic Silver', 109.99],
    ['Nike', 'V2K Run', 'FD0736', 'FD0736-100', 'Summit White/Metallic Silver', 119.99],
    ['Nike', 'Zoom Vomero 5', 'BV1358', 'BV1358-001', 'Vast Grey/Black', 159.99],
    ['Nike', 'Air Max 270', 'AH8050', 'AH8050-100', 'White/Black', 169.99],
    ['Nike', 'Pegasus 41', 'FD2722', 'FD2722-001', 'Black/Anthracite', 139.99],
    ['Adidas', 'Samba OG', 'B75806', 'B75806', 'Cloud White/Core Black', 119.99],
    ['Adidas', 'Gazelle Indoor', 'H06261', 'H06261', 'Collegiate Green/White', 119.99],
    ['Adidas', 'Campus 00s', 'HQ8708', 'HQ8708', 'Core Black/Cloud White', 109.99],
    ['Adidas', 'Handball Spezial', 'DB3021', 'DB3021', 'Navy/Gum', 109.99],
    ['Adidas', 'SL 72 RS', 'IG2132', 'IG2132', 'Blue/White/Red', 99.99],
    ['Adidas', 'Superstar II', 'JI0079', 'JI0079', 'White/Core Black', 119.99],
    ['New Balance', '530', 'MR530SG', 'MR530SG', 'White/Silver/Navy', 119.99],
    ['New Balance', '574', 'ML574EVG', 'ML574EVG', 'Grey/White', 109.99],
    ['New Balance', '9060', 'U9060GRY', 'U9060GRY', 'Grey/Silver', 189.99],
    ['New Balance', '2002R', 'M2002RCC', 'M2002RCC', 'Calm Taupe', 159.99],
    ['New Balance', '1906R', 'M1906RER', 'M1906RER', 'Silver/Black', 169.99],
    ['New Balance', '327', 'MS327CWB', 'MS327CWB', 'White/Black', 119.99],
    ['ASICS', 'GEL-Kayano 31', '1011B867', '1011B867-400', 'Blue Expanse/Grey Blue', 199.99],
    ['ASICS', 'GEL-NYC', '1201A789', '1201A789-103', 'Cream/Oyster Grey', 169.99],
    ['ASICS', 'GEL-1130', '1201A256', '1201A256-112', 'White/Clay Grey', 119.99],
    ['ASICS', 'GT-2160', '1203A275', '1203A275-103', 'White/Shamrock Green', 129.99],
    ['ASICS', 'GEL-Venture 6', '1203A438', '1203A438-020', 'Graphite Grey/Black', 109.99],
    ['Puma', 'Speedcat OG', '398846', '398846-01', 'For All Time Red/White', 109.99],
    ['Puma', 'Palermo', '396463', '396463-05', 'Green/White/Gum', 89.99],
    ['Puma', 'Suede XL', '395205', '395205-02', 'Black/White', 99.99],
    ['Reebok', 'Club C 85', 'AR0456', 'AR0456', 'Chalk/Green', 89.99],
    ['Reebok', 'Classic Leather', '100008491', '100008491', 'White/Light Grey', 99.99],
    ['Converse', 'Chuck 70', '162050C', '162050C', 'Black/Egret', 99.99],
    ['Converse', 'Run Star Hike', '166800C', '166800C', 'Black/White/Gum', 129.99],
    ['Vans', 'Knu Skool', 'VN0009QC', 'VN0009QC6BT', 'Black/True White', 99.99],
    ['Vans', 'Old Skool', 'VN000D3H', 'VN000D3HY28', 'Black/White', 84.99],
    ['Salomon', 'XT-6', 'L47444800', 'L47444800', 'Black/Phantom', 179.99],
    ['Salomon', 'ACS Pro', 'L47299000', 'L47299000', 'Vanilla Ice/Lunar Rock', 199.99],
    ['Hoka', 'Clifton 10', '1162030', '1162030-BWHT', 'Black/White', 179.99],
    ['Hoka', 'Bondi 9', '1162011', '1162011-WWH', 'White/White', 189.99],
    ['On', 'Cloud 6', '3MF1007', '3MF1007-100', 'White/White', 159.99],
    ['On', 'Cloudmonster 2', '3ME1012', '3ME1012-104', 'Undyed White/White', 189.99],
];

function ean13(int $seed): string
{
    $body = sprintf('475%09d', $seed);
    $sum = 0;

    foreach (str_split($body) as $index => $digit) {
        $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
    }

    return $body.((10 - ($sum % 10)) % 10);
}

function money(float $value): string
{
    return number_format($value, 2, '.', '');
}

function listing(array $retailer, array $product, int $productIndex, int $position): array
{
    [$brand, $name, $styleCode, $variantCode, $colour, $basePrice] = $product;
    $externalId = strtoupper(str_replace('-', '', $retailer['slug'])).'-'.sprintf('%05d', $productIndex + 1);
    $currentPrice = round($basePrice * $retailer['price_factor'], 2);
    $onSale = $position % 4 === 0;
    $baseSize = 36 + ($productIndex % 7);
    $sizes = [];

    for ($sizeOffset = 0; $sizeOffset < 5; $sizeOffset++) {
        $size = $baseSize + ($sizeOffset * 0.5);
        $sizes[] = [
            'eu_size' => fmod($size, 1.0) === 0.0 ? (string) (int) $size : (string) $size,
            'in_stock' => ($position + $sizeOffset) % 5 !== 0,
            'price' => $position % 9 === 0 && $sizeOffset === 4
                ? money($currentPrice + 5)
                : null,
        ];
    }

    return [
        'retailer_external_id' => $externalId,
        'retailer_sku' => strtoupper(substr($retailer['slug'], 0, 3)).'-'.$variantCode,
        'gtin' => $productIndex % 6 === 0 ? null : ean13($productIndex + 1),
        'manufacturer_style_code' => $productIndex % 11 === 0 ? null : $styleCode,
        'manufacturer_variant_code' => $variantCode,
        'brand' => $brand,
        'title' => "{$brand} {$name}",
        'colour' => $colour,
        'product_url' => "https://{$retailer['slug']}.example/products/".strtolower($externalId),
        'affiliate_url' => $position % 7 === 0
            ? null
            : "https://track.{$retailer['slug']}.example/click/".strtolower($externalId),
        'current_price' => money($onSale ? $currentPrice * 0.85 : $currentPrice),
        'original_price' => $onSale ? money($currentPrice) : null,
        'currency' => 'EUR',
        'delivery' => [
            'cost' => $retailer['delivery_cost'],
            'min_days' => $retailer['delivery_days'][0],
            'max_days' => $retailer['delivery_days'][1],
            'note_lv' => $position % 8 === 0 ? 'Piegāde uz pakomātu' : null,
            'note_en' => $position % 8 === 0 ? 'Delivery to a parcel locker' : null,
        ],
        'images' => [
            "https://images.{$retailer['slug']}.example/".strtolower($externalId).'-1.webp',
            ...($position % 3 === 0
                ? ["https://images.{$retailer['slug']}.example/".strtolower($externalId).'-2.webp']
                : []),
        ],
        'sizes' => $sizes,
        'active' => true,
        'observed_at' => OBSERVED_AT,
    ];
}

function initialListings(array $retailer, array $products): array
{
    $listings = [];

    for ($position = 0; $position < 25; $position++) {
        $productIndex = ($retailer['offset'] + $position) % count($products);
        $listings[] = listing(
            $retailer,
            $products[$productIndex],
            $productIndex,
            $position,
        );
    }

    return $listings;
}

function updateListings(array $retailer, array $initial, array $products): array
{
    $updated = array_values($initial);
    $updated[0]['current_price'] = money((float) $updated[0]['current_price'] - 10);
    $updated[0]['original_price'] ??= money((float) $updated[0]['current_price'] + 20);
    $updated[1]['current_price'] = $updated[1]['original_price'] ?? $updated[1]['current_price'];
    $updated[1]['original_price'] = null;
    $updated[2]['original_price'] = money((float) $updated[2]['current_price'] + 15);
    $updated[3]['sizes'][1]['in_stock'] = false;
    $updated[4]['sizes'][array_key_last($updated[4]['sizes'])]['in_stock'] = true;
    $updated[5]['sizes'][] = ['eu_size' => '46', 'in_stock' => true, 'price' => null];
    $updated[6]['delivery']['cost'] = '4.49';
    $updated[6]['delivery']['min_days'] = 3;
    $updated[6]['delivery']['max_days'] = 6;
    $updated[7]['title'] .= ' sneakers';
    $updated[8]['colour'] = str_replace('/', ' / ', $updated[8]['colour']);
    $updated[9]['affiliate_url'] .= '?campaign=summer';
    $updated[11]['active'] = false;
    $updated[13]['gtin'] = ean13(900 + $retailer['offset']);

    unset($updated[12]);
    $updated = array_values($updated);

    $newProductIndex = ($retailer['offset'] + 25) % count($products);
    $newListing = listing(
        $retailer,
        $products[$newProductIndex],
        $newProductIndex,
        25,
    );
    $newListing['retailer_external_id'] .= '-NEW';
    $newListing['retailer_sku'] .= '-NEW';
    $updated[] = $newListing;

    return array_map(function (array $item): array {
        $item['observed_at'] = UPDATED_AT;

        return $item;
    }, $updated);
}

function ensureDirectories(): void
{
    foreach (['clean', 'updates', 'invalid', 'expected', 'schemas'] as $directory) {
        if (! is_dir(FIXTURE_ROOT.'/'.$directory)) {
            mkdir(FIXTURE_ROOT.'/'.$directory, 0777, true);
        }
    }
}

function writeJson(string $path, mixed $value): void
{
    file_put_contents(
        $path,
        json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL,
    );
}

function csvSizeValue(array $sizes): string
{
    return implode('|', array_map(
        fn (array $size): string => implode(':', [
            $size['eu_size'],
            $size['in_stock'] ? '1' : '0',
            $size['price'] ?? '',
        ]),
        $sizes,
    ));
}

function writeCsv(string $path, array $listings): void
{
    $stream = fopen($path, 'wb');
    $columns = [
        'external_id', 'sku', 'gtin', 'style_code', 'variant_code', 'brand',
        'title', 'colour', 'product_url', 'affiliate_url', 'price',
        'original_price', 'currency', 'delivery_cost', 'delivery_min_days',
        'delivery_max_days', 'delivery_note_lv', 'delivery_note_en', 'images',
        'sizes', 'active', 'observed_at',
    ];
    fputcsv($stream, $columns, ';', '"', '');

    foreach ($listings as $item) {
        $row = [
            $item['retailer_external_id'],
            $item['retailer_sku'],
            $item['gtin'],
            $item['manufacturer_style_code'],
            $item['manufacturer_variant_code'],
            $item['brand'],
            $item['title'],
            $item['colour'],
            $item['product_url'],
            $item['affiliate_url'],
            str_replace('.', ',', $item['current_price']),
            $item['original_price'] === null ? null : str_replace('.', ',', $item['original_price']),
            $item['currency'],
            $item['delivery']['cost'] === null
                ? null
                : str_replace('.', ',', $item['delivery']['cost']),
            $item['delivery']['min_days'],
            $item['delivery']['max_days'],
            $item['delivery']['note_lv'],
            $item['delivery']['note_en'],
            implode('|', $item['images']),
            csvSizeValue($item['sizes']),
            $item['active'] ? '1' : '0',
            $item['observed_at'],
        ];
        fputcsv($stream, $row, ';', '"', '');
    }

    fclose($stream);
}

function writeJsonFeed(string $path, array $retailer, array $listings, string $generatedAt): void
{
    writeJson($path, [
        'retailer' => [
            'code' => $retailer['slug'],
            'name' => $retailer['name'],
        ],
        'generated_at' => $generatedAt,
        'products' => $listings,
    ]);
}

function writeJsonLines(string $path, array $listings): void
{
    $lines = array_map(
        fn (array $listing): string => json_encode(
            $listing,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ),
        $listings,
    );

    file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL);
}

function writeXml(string $path, array $retailer, array $listings, string $generatedAt): void
{
    $xml = new XMLWriter;
    $xml->openMemory();
    $xml->setIndent(true);
    $xml->startDocument('1.0', 'UTF-8');
    $xml->startElementNs(null, 'catalogue', 'https://feeds.shoefinder.example/v1');
    $xml->writeAttribute('retailer', $retailer['slug']);
    $xml->writeAttribute('generated-at', $generatedAt);

    foreach ($listings as $item) {
        $xml->startElement('product');
        $xml->writeAttribute('external-id', $item['retailer_external_id']);
        $xml->writeElement('sku', $item['retailer_sku']);

        foreach ([
            'gtin' => $item['gtin'],
            'style-code' => $item['manufacturer_style_code'],
            'variant-code' => $item['manufacturer_variant_code'],
            'brand' => $item['brand'],
            'title' => $item['title'],
            'colour' => $item['colour'],
            'product-url' => $item['product_url'],
            'affiliate-url' => $item['affiliate_url'],
        ] as $element => $value) {
            if ($value !== null) {
                $xml->writeElement($element, (string) $value);
            }
        }

        $xml->startElement('price');
        $xml->writeAttribute('currency', $item['currency']);
        $xml->writeAttribute('current', $item['current_price']);
        if ($item['original_price'] !== null) {
            $xml->writeAttribute('original', $item['original_price']);
        }
        $xml->endElement();

        $xml->startElement('delivery');
        if ($item['delivery']['cost'] !== null) {
            $xml->writeAttribute('cost', $item['delivery']['cost']);
        }
        $xml->writeAttribute('min-days', (string) $item['delivery']['min_days']);
        $xml->writeAttribute('max-days', (string) $item['delivery']['max_days']);
        if ($item['delivery']['note_lv'] !== null) {
            $xml->writeElement('note-lv', $item['delivery']['note_lv']);
            $xml->writeElement('note-en', $item['delivery']['note_en']);
        }
        $xml->endElement();

        $xml->startElement('images');
        foreach ($item['images'] as $image) {
            $xml->writeElement('image', $image);
        }
        $xml->endElement();

        $xml->startElement('sizes');
        foreach ($item['sizes'] as $size) {
            $xml->startElement('size');
            $xml->writeAttribute('eu', $size['eu_size']);
            $xml->writeAttribute('in-stock', $size['in_stock'] ? 'true' : 'false');
            if ($size['price'] !== null) {
                $xml->writeAttribute('price', $size['price']);
            }
            $xml->endElement();
        }
        $xml->endElement();

        $xml->writeElement('active', $item['active'] ? 'true' : 'false');
        $xml->writeElement('observed-at', $item['observed_at']);
        $xml->endElement();
    }

    $xml->endElement();
    $xml->endDocument();
    file_put_contents($path, $xml->outputMemory());
}

function writeFeed(
    string $directory,
    array $retailer,
    array $listings,
    string $generatedAt,
): void {
    $path = FIXTURE_ROOT."/{$directory}/{$retailer['slug']}.{$retailer['format']}";

    match ($retailer['format']) {
        'csv' => writeCsv($path, $listings),
        'json' => writeJsonFeed($path, $retailer, $listings, $generatedAt),
        'jsonl' => writeJsonLines($path, $listings),
        'xml' => writeXml($path, $retailer, $listings, $generatedAt),
    };
}

function expectedOutcomes(array $retailer, array $initial, array $updated): array
{
    $initialById = [];
    foreach ($initial as $item) {
        $initialById[$item['retailer_external_id']] = $item;
    }

    $updatedById = [];
    foreach ($updated as $item) {
        $updatedById[$item['retailer_external_id']] = $item;
    }

    $outcomes = [];
    foreach ($updatedById as $externalId => $item) {
        if (! isset($initialById[$externalId])) {
            $outcome = 'created';
        } elseif ($item['gtin'] !== $initialById[$externalId]['gtin']) {
            $outcome = 'manual_review';
        } elseif (! $item['active']) {
            $outcome = 'unavailable';
        } elseif ($item === array_replace($initialById[$externalId], ['observed_at' => UPDATED_AT])) {
            $outcome = 'unchanged';
        } else {
            $outcome = 'updated';
        }

        $outcomes[] = [
            'retailer' => $retailer['slug'],
            'retailer_external_id' => $externalId,
            'outcome' => $outcome,
        ];
    }

    foreach (array_diff_key($initialById, $updatedById) as $externalId => $_item) {
        $outcomes[] = [
            'retailer' => $retailer['slug'],
            'retailer_external_id' => $externalId,
            'outcome' => 'missing',
        ];
    }

    return $outcomes;
}

function writeInvalidFixtures(): void
{
    $csv = <<<'CSV'
external_id;sku;gtin;style_code;variant_code;brand;title;colour;product_url;affiliate_url;price;original_price;currency;delivery_cost;delivery_min_days;delivery_max_days;delivery_note_lv;delivery_note_en;images;sizes;active;observed_at
;;12345;BAD-1;BAD-1-001;Test Brand;;Black;not-a-url;;-10,00;-20,00;EURO;-1,00;6;2;;;https://images.sole-market.example/bad.webp;42:1:-5,00|42:1:;maybe;not-a-date
"BROKEN;ROW
CSV;
    file_put_contents(FIXTURE_ROOT.'/invalid/sole-market-invalid.csv', $csv.PHP_EOL);

    writeJson(FIXTURE_ROOT.'/invalid/urban-step-invalid.json', [
        'retailer' => ['code' => 'urban-step', 'name' => 'Urban Step'],
        'generated_at' => 'invalid-date',
        'products' => [
            [
                'retailer_external_id' => 'US-INVALID-1',
                'retailer_sku' => 'DUPLICATE-SKU',
                'gtin' => '4750000000008',
                'brand' => 'Test Brand',
                'title' => 'Conflicting identity product',
                'colour' => 'Blue',
                'product_url' => 'https://urban-step.example/products/invalid-1',
                'current_price' => '89.99',
                'original_price' => '79.99',
                'currency' => 'USD',
                'active' => true,
                'unexpected_field' => 'must be reported',
                'sizes' => [['eu_size' => 'forty-two', 'in_stock' => true]],
            ],
            [
                'retailer_external_id' => 'US-INVALID-2',
                'retailer_sku' => 'DUPLICATE-SKU',
                'gtin' => '4750000000008',
                'brand' => 'Other Brand',
                'title' => 'Second conflicting product',
                'colour' => 'Red',
                'product_url' => 'https://urban-step.example/products/invalid-2',
                'current_price' => '99.99',
                'currency' => 'EUR',
                'active' => true,
                'sizes' => [],
            ],
        ],
    ]);

    $jsonLines = [
        '{"retailer_external_id":"SP-INVALID-1","retailer_sku":"SP-1","gtin":"4750000000008","brand":"Test Brand","title":"Valid syntax","colour":"Black","product_url":"https://sneaker-point.example/products/invalid-1","current_price":"99.99","currency":"EUR","active":true,"sizes":[{"eu_size":"42","in_stock":true},{"eu_size":"42","in_stock":false}]}',
        '{"retailer_external_id":"SP-BROKEN","title":"Missing closing brace"',
        '{"retailer_external_id":"SP-INVALID-3","retailer_sku":"SP-3","brand":"Test Brand","title":"Bad boolean","colour":"White","product_url":"ftp://example.test/product","current_price":"free","currency":"EUR","active":"yes","sizes":[]}',
    ];
    file_put_contents(
        FIXTURE_ROOT.'/invalid/sneaker-point-invalid.jsonl',
        implode(PHP_EOL, $jsonLines).PHP_EOL,
    );

    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<catalogue xmlns="https://feeds.shoefinder.example/v1" retailer="apavu-nams" generated-at="2026-07-29T09:00:00+03:00">
  <product external-id="AN-INVALID-1">
    <sku>AN-INVALID-1</sku>
    <brand>Test Brand</brand>
    <title>Broken XML product</title>
    <colour>Black</colour>
    <product-url>https://apavu-nams.example/products/invalid-1</product-url>
    <price currency="EUR" current="99.99"/>
    <sizes><size eu="42" in-stock="true"></sizes>
  </product>
</catalogue>
XML;
    file_put_contents(FIXTURE_ROOT.'/invalid/apavu-nams-invalid.xml', $xml.PHP_EOL);

    writeJson(FIXTURE_ROOT.'/expected/validation-errors.json', [
        ['file' => 'sole-market-invalid.csv', 'record' => 2, 'field' => 'retailer_external_id', 'code' => 'identity_missing'],
        ['file' => 'sole-market-invalid.csv', 'record' => 2, 'field' => 'gtin', 'code' => 'gtin_invalid'],
        ['file' => 'sole-market-invalid.csv', 'record' => 2, 'field' => 'title', 'code' => 'required'],
        ['file' => 'sole-market-invalid.csv', 'record' => 2, 'field' => 'current_price', 'code' => 'money_negative'],
        ['file' => 'sole-market-invalid.csv', 'record' => 2, 'field' => 'original_price', 'code' => 'money_negative'],
        ['file' => 'sole-market-invalid.csv', 'record' => 2, 'field' => 'original_price', 'code' => 'original_price_below_current'],
        ['file' => 'sole-market-invalid.csv', 'record' => 2, 'field' => 'currency', 'code' => 'currency_unsupported'],
        ['file' => 'sole-market-invalid.csv', 'record' => 2, 'field' => 'product_url', 'code' => 'url_invalid'],
        ['file' => 'sole-market-invalid.csv', 'record' => 2, 'field' => 'delivery_cost', 'code' => 'money_negative'],
        ['file' => 'sole-market-invalid.csv', 'record' => 2, 'field' => 'delivery_max_days', 'code' => 'delivery_range_invalid'],
        ['file' => 'sole-market-invalid.csv', 'record' => 2, 'field' => 'sizes.0.price', 'code' => 'money_negative'],
        ['file' => 'sole-market-invalid.csv', 'record' => 2, 'field' => 'observed_at', 'code' => 'date_invalid'],
        ['file' => 'sole-market-invalid.csv', 'record' => 3, 'field' => null, 'code' => 'syntax_error'],
        ['file' => 'urban-step-invalid.json', 'record' => 1, 'field' => 'retailer_sku', 'code' => 'identity_duplicate'],
        ['file' => 'urban-step-invalid.json', 'record' => 1, 'field' => 'gtin', 'code' => 'identity_conflict'],
        ['file' => 'urban-step-invalid.json', 'record' => 1, 'field' => 'unexpected_field', 'code' => 'field_unknown'],
        ['file' => 'sneaker-point-invalid.jsonl', 'record' => 1, 'field' => 'sizes', 'code' => 'size_duplicate'],
        ['file' => 'sneaker-point-invalid.jsonl', 'record' => 2, 'field' => null, 'code' => 'syntax_error'],
        ['file' => 'sneaker-point-invalid.jsonl', 'record' => 3, 'field' => 'active', 'code' => 'boolean_invalid'],
        ['file' => 'sneaker-point-invalid.jsonl', 'record' => 3, 'field' => 'product_url', 'code' => 'url_invalid'],
        ['file' => 'sneaker-point-invalid.jsonl', 'record' => 3, 'field' => 'current_price', 'code' => 'money_invalid'],
        ['file' => 'apavu-nams-invalid.xml', 'record' => null, 'field' => null, 'code' => 'syntax_error'],
    ]);
}

ensureDirectories();

$allInitial = [];
$allUpdated = [];
$allOutcomes = [];

foreach ($retailers as $retailer) {
    $initial = initialListings($retailer, $products);
    $updated = updateListings($retailer, $initial, $products);
    writeFeed('clean', $retailer, $initial, OBSERVED_AT);
    writeFeed('updates', $retailer, $updated, UPDATED_AT);
    $allInitial[$retailer['slug']] = $initial;
    $allUpdated[$retailer['slug']] = $updated;
    array_push($allOutcomes, ...expectedOutcomes($retailer, $initial, $updated));
}

writeJson(FIXTURE_ROOT.'/expected/initial-normalized.json', $allInitial);
writeJson(FIXTURE_ROOT.'/expected/update-normalized.json', [
    'records' => $allUpdated,
    'outcomes' => $allOutcomes,
    'missing_policy' => 'report_only',
]);
writeInvalidFixtures();

echo "Product-feed fixtures generated.\n";
