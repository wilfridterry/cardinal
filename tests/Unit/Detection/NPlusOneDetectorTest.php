<?php

declare(strict_types=1);

use Cardinal\Detection\NPlusOneDetector;

it('detects an N+1 pattern above the threshold', function () {
    $detector = new NPlusOneDetector(threshold: 10);

    $issues = $detector->detect([
        ['hash' => 'a', 'template' => 'select * from comments where post_id = ?', 'count' => 25, 'total_ms' => 50.0, 'max_ms' => 4.0],
    ]);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]['type'])->toBe('n_plus_one')
        ->and($issues[0]['payload']['repeats'])->toBe(25);
});

it('ignores a query repeated below the threshold', function () {
    $detector = new NPlusOneDetector(threshold: 10);

    $issues = $detector->detect([
        ['hash' => 'a', 'template' => 'select * from comments where post_id = ?', 'count' => 5, 'total_ms' => 10.0, 'max_ms' => 4.0],
    ]);

    expect($issues)->toBeEmpty();
});

it('only flags queries with an equality predicate (FK-like)', function () {
    $detector = new NPlusOneDetector(threshold: 10);

    // a repeated aggregate with no equality predicate is not an N+1
    $issues = $detector->detect([
        ['hash' => 'a', 'template' => 'select count(*) from logs', 'count' => 50, 'total_ms' => 10.0, 'max_ms' => 1.0],
    ]);

    expect($issues)->toBeEmpty();
});

it('flags an in-list repeated query as N+1', function () {
    $detector = new NPlusOneDetector(threshold: 10);

    $issues = $detector->detect([
        ['hash' => 'a', 'template' => 'select * from users where id in (?)', 'count' => 15, 'total_ms' => 20.0, 'max_ms' => 2.0],
    ]);

    expect($issues)->toHaveCount(1);
});
