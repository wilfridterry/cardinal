# Cardinal

Production query health monitoring for Laravel. Catch slow queries, N+1 bugs, and missing indexes — with AI-powered fix suggestions and ready-to-run migrations.

## Features

- **Slow Query Detection** — track queries exceeding your threshold (default 500ms)
- **N+1 Detection** — spot repeated query patterns within a single request
- **Missing Index Detection** — identify unindexed columns in slow queries
- **AI-Powered Analysis** — diagnosis and migration code via Claude or OpenAI (optional, BYO key)
- **Zero Production Overhead** — configurable sampling, ignore lists, buffered processing
- **Privacy First** — only normalized SQL templates leave your server, never values

## Installation

```bash
composer require cardinal/laravel
php artisan migrate
```

Publish config (optional):

```bash
php artisan vendor:publish --provider="Cardinal\CardinalServiceProvider" --tag="config"
```

## Configuration

```php
// config/cardinal.php
return [
    'enabled'               => env('CARDINAL_ENABLED', true),
    'sample_rate'           => env('CARDINAL_SAMPLE_RATE', 1.0),
    'slow_threshold_ms'     => 500,
    'n_plus_one_threshold'  => 10,
    'ignore' => [
        'tables' => ['telescope_*', 'pulse_*', 'jobs', 'sessions', 'cache'],
        'paths'  => ['horizon*', 'telescope*'],
    ],
    'storage' => [
        'retention_days' => 14,
    ],
    'explain' => [
        'enabled'        => true,
        'on_demand_only' => true,
    ],
    'ai' => [
        'provider' => env('CARDINAL_AI', null),  // 'anthropic' | 'openai' | null
        'api_key'  => env('CARDINAL_AI_KEY'),
        'model'    => env('CARDINAL_AI_MODEL', 'claude-sonnet-4-6'),
    ],
];
```

## Usage

### Report all issues

```bash
php artisan cardinal:report
php artisan cardinal:report --type=slow
php artisan cardinal:report --type=n_plus_one
php artisan cardinal:report --type=missing_index
```

### AI analysis of a specific query

```bash
php artisan cardinal:analyze ab12cd34ef56
```

Requires `CARDINAL_AI_KEY` in `.env`. Returns diagnosis, root cause, suggested migration and Eloquent pattern.

### Generate and apply a fix

```bash
php artisan cardinal:fix ab12cd34ef56
```

Generates a migration file. You review and run it yourself — Cardinal never auto-applies migrations.

### Signal a deploy (Cloud, Phase 3)

```bash
php artisan cardinal:deploy --sha=abc123 --branch=main
```

## How It Works

### Fingerprinting

Every SQL query is normalized into a template — all literals replaced with `?`, `IN` lists collapsed:

```
Raw:      SELECT * FROM orders WHERE user_id = 42 AND status IN (1, 2, 3)
Template: SELECT * FROM orders WHERE user_id = ? AND status IN (?)
Hash:     ab12cd34ef56...
```

Identical templates across requests share one fingerprint, enabling accurate aggregation and N+1 detection.

### Privacy guarantee

The Fingerprinter strips all literals before any data is persisted or transmitted. A CI test asserts this invariant — it fails if any raw number or string value leaks into a template.

### Detectors

| Detector | Trigger |
|---|---|
| `SlowQueryDetector` | `max_ms >= slow_threshold_ms` |
| `NPlusOneDetector` | same fingerprint repeats `>= n_plus_one_threshold` times per request |
| `MissingIndexDetector` | slow query WHERE/JOIN columns not covered by any index |

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 12
- MySQL 5.7+ or PostgreSQL 12+ or SQLite (testing)

## Testing

```bash
composer test
```

## Roadmap

- [x] v0.1 — Slow / N+1 / missing index detection, AI fix suggestions, migration generator
- [ ] v0.2 — Cloud transport (batched metrics ingestion)
- [ ] Cloud — Deploy-linked regression detection, Filament dashboard, Stripe billing

## License

MIT
