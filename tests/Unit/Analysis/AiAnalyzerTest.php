<?php

namespace Cardinal\Tests\Unit\Analysis;

use Cardinal\Analysis\AiAnalyzer;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertArrayHasKey;
use function PHPUnit\Framework\assertNotEmpty;

it('returns structured response from ai provider', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'diagnosis' => 'Missing index on user_id',
                    'root_cause' => 'Full table scan on orders',
                    'fix_migration' => "Schema::table('orders', fn(\$t) => \$t->index('user_id'));",
                    'fix_eloquent' => "Order::with('user')->get();",
                    'expected_impact' => '10x faster',
                    'confidence' => 'high',
                ]),
            ]],
        ], 200),
    ]);

    $analyzer = new AiAnalyzer([
        'provider' => 'anthropic',
        'api_key' => 'test-key',
        'model' => 'claude-sonnet-4-6',
    ]);

    $result = $analyzer->analyze(
        template: 'select * from orders where user_id = ?',
        explainJson: [['opcode' => 'SCAN', 'p2' => 0]],
        ddl: 'CREATE TABLE orders (id INT, user_id INT)',
    );

    assertArrayHasKey('diagnosis', $result);
    assertArrayHasKey('root_cause', $result);
    assertArrayHasKey('fix_migration', $result);
    assertArrayHasKey('fix_eloquent', $result);
    assertArrayHasKey('expected_impact', $result);
    assertArrayHasKey('confidence', $result);
    assertNotEmpty($result['diagnosis']);
});

it('returns empty array when no api key configured', function () {
    $analyzer = new AiAnalyzer([
        'provider' => 'anthropic',
        'api_key' => null,
        'model' => 'claude-sonnet-4-6',
    ]);

    $result = $analyzer->analyze('select * from users', [], '');

    assertEquals([], $result);
});

it('returns empty array when provider is null', function () {
    $analyzer = new AiAnalyzer([
        'provider' => null,
        'api_key' => 'key',
        'model' => 'claude-sonnet-4-6',
    ]);

    $result = $analyzer->analyze('select * from users', [], '');

    assertEquals([], $result);
});

it('handles malformed json response gracefully', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => 'not json at all',
            ]],
        ], 200),
    ]);

    $analyzer = new AiAnalyzer([
        'provider' => 'anthropic',
        'api_key' => 'test-key',
        'model' => 'claude-sonnet-4-6',
    ]);

    $result = $analyzer->analyze('select * from users', [], '');

    assertEquals([], $result);
});

it('handles http error gracefully', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    $analyzer = new AiAnalyzer([
        'provider' => 'anthropic',
        'api_key' => 'bad-key',
        'model' => 'claude-sonnet-4-6',
    ]);

    $result = $analyzer->analyze('select * from users', [], '');

    assertEquals([], $result);
});

it('caches result by prompt hash', function () {
    $callCount = 0;

    Http::fake([
        'api.anthropic.com/*' => function () use (&$callCount) {
            $callCount++;
            return Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode([
                        'diagnosis' => 'Missing index',
                        'root_cause' => 'Scan',
                        'fix_migration' => '',
                        'fix_eloquent' => '',
                        'expected_impact' => 'fast',
                        'confidence' => 'high',
                    ]),
                ]],
            ], 200);
        },
    ]);

    $analyzer = new AiAnalyzer([
        'provider' => 'anthropic',
        'api_key' => 'test-key',
        'model' => 'claude-sonnet-4-6',
    ]);

    $analyzer->analyze('select * from users where id = ?', [], 'CREATE TABLE users');
    $analyzer->analyze('select * from users where id = ?', [], 'CREATE TABLE users');

    assertEquals(1, $callCount);
});
