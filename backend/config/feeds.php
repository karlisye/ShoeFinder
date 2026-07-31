<?php

return [
    'admin' => [
        'max_records' => 5000,
        'queue' => env('FEED_IMPORT_QUEUE', 'imports'),
    ],

    'retailers' => [
        'sole-market' => [
            'format' => 'csv',
        ],
        'urban-step' => [
            'format' => 'json',
        ],
        'sneaker-point' => [
            'format' => 'jsonl',
        ],
        'apavu-nams' => [
            'format' => 'xml',
        ],
    ],
];
