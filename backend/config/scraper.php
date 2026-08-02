<?php

return [
    'enabled' => filter_var(env('SCRAPER_ENABLED', true), FILTER_VALIDATE_BOOL),
    'queue' => env('SCRAPE_QUEUE', 'scrapes'),
    'python_binary' => env('SCRAPER_PYTHON_BINARY', 'python3'),
    'timeout_seconds' => max(5, (int) env('SCRAPER_TIMEOUT_SECONDS', 30)),
    'crawl_delay_ms' => max(0, (int) env('SCRAPER_CRAWL_DELAY_MS', 2000)),
    'user_agent' => env(
        'SCRAPER_USER_AGENT',
        'ShoeFinderScraper/1.0 (+'.env('APP_URL', 'http://localhost').')',
    ),
    'retailers' => [
        'ballzy' => [
            'adapter' => 'ballzy',
            'hosts' => ['ballzy.eu'],
            'path_prefixes' => ['/en/product/', '/lv/product/'],
        ],
    ],
];
