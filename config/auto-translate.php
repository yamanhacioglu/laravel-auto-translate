
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default source language
    |--------------------------------------------------------------------------
    */
    'default_source_language' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'enabled' => true,
        'connection' => env('QUEUE_CONNECTION', 'database'),
        'queue' => 'translations',
    ],
];
