<?php

declare(strict_types=1);

use Cardinal\Recording\QueryRecorder;
use Cardinal\Recording\RequestContext;

function makeRecorder(array $overrides = []): QueryRecorder
{
    $config = array_replace_recursive([
        'enabled' => true,
        'sample_rate' => 1.0,
        'slow_threshold_ms' => 500,
        'n_plus_one_threshold' => 10,
        'ignore' => [
            'tables' => ['sessions', 'cache', 'telescope_*'],
            'paths' => ['horizon*'],
        ],
    ], $overrides);

    return new QueryRecorder($config);
}

it('records a query into the per-request buffer', function () {
    $r = makeRecorder();
    $r->record('select * from users where id = 5', 12.0);

    expect($r->buffer())->toHaveCount(1);
});

it('aggregates repeated queries by fingerprint', function () {
    $r = makeRecorder();
    $r->record('select * from users where id = 1', 10.0);
    $r->record('select * from users where id = 2', 20.0);
    $r->record('select * from orders where id = 1', 5.0);

    // 2 distinct fingerprints
    expect($r->buffer())->toHaveCount(2);

    $users = collect($r->buffer())->firstWhere('template', 'select * from users where id = ?');
    expect($users['count'])->toBe(2)
        ->and($users['total_ms'])->toBe(30.0)
        ->and($users['max_ms'])->toBe(20.0);
});

it('skips queries touching ignored tables', function () {
    $r = makeRecorder();
    $r->record('select * from sessions where id = 1', 5.0);
    $r->record('insert into telescope_entries (uuid) values (1)', 5.0);

    expect($r->buffer())->toBeEmpty();
});

it('records queries on non-ignored tables', function () {
    $r = makeRecorder();
    $r->record('select * from sessions where id = 1', 5.0);
    $r->record('select * from users where id = 1', 5.0);

    expect($r->buffer())->toHaveCount(1);
});

it('drops everything when sample_rate is zero', function () {
    $r = makeRecorder(['sample_rate' => 0.0]);
    $r->record('select * from users where id = 1', 5.0);

    expect($r->shouldSample())->toBeFalse();
});

it('always samples when sample_rate is one', function () {
    $r = makeRecorder(['sample_rate' => 1.0]);

    expect($r->shouldSample())->toBeTrue();
});

it('resets the buffer', function () {
    $r = makeRecorder();
    $r->record('select * from users where id = 1', 5.0);
    $r->reset();

    expect($r->buffer())->toBeEmpty();
});

it('tracks a request context', function () {
    $r = makeRecorder();
    $r->setContext(RequestContext::forJob('App\\Jobs\\Foo'));

    expect($r->context()->name)->toBe('App\\Jobs\\Foo');
});
