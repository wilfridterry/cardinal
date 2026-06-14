# I Built a Free Laravel Package That Catches Slow Queries and Writes the Migration For You

Your production database is slow. You know it. Your users know it. But you don't know *when* it started or *which deploy* broke it.

Yesterday's deploy made some query 4x slower. Telescope shows "current state" — but not the regression. Pulse tells you "queries are slow" — but not what changed. You're left digging through git logs and running EXPLAIN manually.

I built **Cardinal** to answer one question: "Which deploy made which query slow?"

## The Problem

Let's say you deploy at 3pm. By 6pm, your error tracking shows increased timeout exceptions. You check Telescope — queries look fine now. You check git history — 47 commits since 3pm. You run EXPLAIN on 10 queries manually. Stress.

This is the "production detective work" pattern. Every team does it. Most solve it by:

1. Keeping spreadsheets of "known slow queries"
2. Setting up separate query monitoring (expensive SaaS, usually APM)
3. Running `slower:analyze` locally in dev (misses production patterns)
4. Doing nothing and accepting slow deploys

**Cardinal does the detective work for you.**

## What It Does

Install the free Laravel package:

```bash
composer require cardinal/laravel
```

That's it. No setup. It hooks into your database automatically.

### It Catches Three Things

**1. Slow queries** — tracks every query, alerts when p95 crosses your threshold (default 500ms).

**2. N+1 queries** — detects when the same fingerprinted query repeats inside a single request.

```
Warning: N+1: SELECT * FROM products WHERE id = ? [150 repeats in single request]
Location: app/Http/Controllers/OrderController.php:42
```

**3. Missing indexes** — parses WHERE and JOIN conditions, checks your schema, suggests an index if a leading column is uncovered.

### The AI Part

Run `php artisan cardinal:analyze {id}` and Cardinal:

1. Gets the query template and its EXPLAIN output
2. Reads your table schema (columns, indexes, constraints)
3. Sends context to Claude or OpenAI (your API key, your cost)
4. Returns a structured diagnosis + ready-to-run migration

```
$ php artisan cardinal:analyze slow-query-123

Diagnosis:
  Query was doing a full table scan of 2.1M rows.
  Missing composite index on (user_id, status).

Fix migration generated:
  php artisan cardinal:fix slow-query-123

Expected improvement:
  p95 latency: 1200ms to 120ms (~10x faster)
```

## Real Example

```php
// OrderController.php
$orders = Order::with('items')->get();

foreach ($orders as $order) {
    $order->customer->name; // triggers N+1
}
```

Cardinal catches this automatically:

```
N+1 Detected
Query:    SELECT * FROM customers WHERE id = ?
Repeats:  247 times in single request
Location: app/Http/Controllers/OrderController.php:18

Fix: Order::with('items', 'customer')->get();
```

## How It Works

- Hooks into `DB::listen` — zero changes to your code
- Fingerprints each query (strips all literals, normalizes whitespace)
- Buffers per-request in memory — no DB write on every query
- Detects patterns in `terminating()` hook
- Stores only issues and aggregates, never raw query values

**Overhead:** listener adds less than 0.05ms per query in recording mode. EXPLAIN and schema inspection run only on-demand via artisan commands, never automatically in production.

### Privacy

The fingerprinter strips all literals before anything is stored or sent. This is verified by a dedicated CI test that asserts no raw values pass through. Only normalized templates like `select * from orders where user_id = ?` are ever stored.

## Installation

```bash
composer require cardinal/laravel
php artisan migrate
php artisan cardinal:report
```

Supports Laravel 10, 11, 12, 13 and PHP 8.2+.

## Why Free?

The free package is complete and useful forever. You get continuous monitoring, AI analysis with your own key, and generated migrations. No account required.

**Cardinal Cloud** (paid, waitlist open) will add production history, deploy-to-regression tracking, and team alerts — starting at $19/month.

**Website:** https://cardinal.dev
**GitHub:** https://github.com/wilfridterry/query-guard
**Packagist:** https://packagist.org/packages/cardinal/laravel

Open an issue if something doesn't work. Feedback directly shapes what gets built next.

#laravel #php #database #performance #opensource
