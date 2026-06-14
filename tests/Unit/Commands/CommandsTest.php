<?php

namespace Cardinal\Tests\Unit\Commands;

use Cardinal\Models\Issue;
use Illuminate\Support\Facades\Http;

it('report command outputs issues table', function () {
    Issue::create([
        'fingerprint' => 'abc123',
        'template'    => 'select * from orders where user_id = ?',
        'type'        => 'slow',
        'max_ms'      => 1200,
        'count'       => 50,
        'last_seen_at' => now(),
    ]);

    $this->artisan('cardinal:report')
        ->expectsOutputToContain('abc123')
        ->assertExitCode(0);
});

it('report command shows no issues message when table empty', function () {
    $this->artisan('cardinal:report')
        ->expectsOutputToContain('No issues')
        ->assertExitCode(0);
});

it('report command accepts type filter', function () {
    Issue::create([
        'fingerprint' => 'abc123',
        'template'    => 'select * from orders where user_id = ?',
        'type'        => 'slow',
        'max_ms'      => 1200,
        'count'       => 50,
        'last_seen_at' => now(),
    ]);

    Issue::create([
        'fingerprint' => 'def456',
        'template'    => 'select * from posts where author_id = ?',
        'type'        => 'n_plus_one',
        'max_ms'      => 50,
        'count'       => 200,
        'last_seen_at' => now(),
    ]);

    $this->artisan('cardinal:report', ['--type' => 'slow'])
        ->expectsOutputToContain('abc123')
        ->assertExitCode(0);
});

it('analyze command outputs ai analysis for existing issue', function () {
    config()->set('cardinal.ai.api_key', 'test-key');
    config()->set('cardinal.ai.provider', 'anthropic');
    config()->set('cardinal.ai.model', 'claude-sonnet-4-6');

    Issue::create([
        'fingerprint' => 'abc123',
        'template'    => 'select * from orders where user_id = ?',
        'type'        => 'slow',
        'max_ms'      => 1200,
        'count'       => 50,
        'last_seen_at' => now(),
    ]);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'diagnosis'       => 'Missing index on user_id',
                    'root_cause'      => 'Full table scan',
                    'fix_migration'   => "Schema::table('orders', fn(\$t) => \$t->index('user_id'));",
                    'fix_eloquent'    => "Order::with('user')->get();",
                    'expected_impact' => '10x faster',
                    'confidence'      => 'high',
                ]),
            ]],
        ], 200),
    ]);

    $this->artisan('cardinal:analyze', ['fingerprint' => 'abc123'])
        ->expectsOutputToContain('Missing index')
        ->assertExitCode(0);
});

it('analyze command shows error for unknown fingerprint', function () {
    $this->artisan('cardinal:analyze', ['fingerprint' => 'notexist'])
        ->expectsOutputToContain('not found')
        ->assertExitCode(1);
});

it('fix command outputs migration for missing index issue', function () {
    Issue::create([
        'fingerprint' => 'abc123',
        'template'    => 'select * from orders where user_id = ?',
        'type'        => 'missing_index',
        'max_ms'      => 600,
        'count'       => 100,
        'payload'     => ['missing_columns' => ['user_id'], 'table' => 'orders'],
        'last_seen_at' => now(),
    ]);

    $this->artisan('cardinal:fix', ['fingerprint' => 'abc123'])
        ->expectsOutputToContain('user_id')
        ->assertExitCode(0);
});

it('fix command shows error for unknown fingerprint', function () {
    $this->artisan('cardinal:fix', ['fingerprint' => 'notexist'])
        ->expectsOutputToContain('not found')
        ->assertExitCode(1);
});
