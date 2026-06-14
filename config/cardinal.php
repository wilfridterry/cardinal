<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enable / disable the package entirely
    |--------------------------------------------------------------------------
    */
    'enabled' => env('CARDINAL_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Sampling
    | 1.0 = every request, 0.1 = 10 % of requests (random, decided once per
    | request so all queries of that request are recorded or none are).
    |--------------------------------------------------------------------------
    */
    'sample_rate' => env('CARDINAL_SAMPLE_RATE', 1.0),

    /*
    |--------------------------------------------------------------------------
    | Detection thresholds
    |--------------------------------------------------------------------------
    */
    'slow_threshold_ms'    => 500,
    'n_plus_one_threshold' => 10,

    /*
    |--------------------------------------------------------------------------
    | Ignore lists — tables and URL path prefixes to skip entirely
    |--------------------------------------------------------------------------
    */
    'ignore' => [
        'tables' => ['telescope_*', 'pulse_*', 'jobs', 'sessions', 'cache'],
        'paths'  => ['horizon*', 'telescope*'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Local storage
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'connection'     => null,   // null = use default DB connection
        'retention_days' => 14,
    ],

    /*
    |--------------------------------------------------------------------------
    | EXPLAIN (on-demand only — never run automatically in prod)
    |--------------------------------------------------------------------------
    */
    'explain' => [
        'enabled'       => true,
        'on_demand_only' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI analysis (BYO key — cloud plan uses the cloud's key automatically)
    |--------------------------------------------------------------------------
    */
    'ai' => [
        'provider' => env('CARDINAL_AI', 'anthropic'), // anthropic | openai | null
        'api_key'  => env('CARDINAL_AI_KEY'),
        'model'    => env('CARDINAL_AI_MODEL', 'claude-sonnet-4-6'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloud transport (Phase 3 — disabled by default in the open-source package)
    |--------------------------------------------------------------------------
    */
    'cloud' => [
        'enabled'    => env('CARDINAL_CLOUD', false),
        'token'      => env('CARDINAL_TOKEN'),
        'endpoint'   => 'https://app.cardinal.dev/api/v1',
        'flush_every' => 60, // seconds — batch sent in terminating()
    ],

];
