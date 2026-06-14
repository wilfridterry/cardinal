<?php

declare(strict_types=1);

use Cardinal\Detection\SlowQueryDetector;

it('detects a query slower than the threshold', function () {
    $detector = new SlowQueryDetector(slowThresholdMs: 500);

    $issues = $detector->detect([
        ['hash' => 'a', 'template' => 'select * from orders where id = ?', 'count' => 1, 'total_ms' => 800.0, 'max_ms' => 800.0],
    ]);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]['type'])->toBe('slow')
        ->and($issues[0]['hash'])->toBe('a');
});

it('ignores a query faster than the threshold', function () {
    $detector = new SlowQueryDetector(slowThresholdMs: 500);

    $issues = $detector->detect([
        ['hash' => 'a', 'template' => 'select 1', 'count' => 1, 'total_ms' => 10.0, 'max_ms' => 10.0],
    ]);

    expect($issues)->toBeEmpty();
});

it('uses max_ms not average to detect spikes', function () {
    $detector = new SlowQueryDetector(slowThresholdMs: 500);

    // average is low (510/2 = 255) but one execution spiked to 500+
    $issues = $detector->detect([
        ['hash' => 'a', 'template' => 'select * from t where id = ?', 'count' => 2, 'total_ms' => 610.0, 'max_ms' => 600.0],
    ]);

    expect($issues)->toHaveCount(1);
});

it('returns no issues for an empty buffer', function () {
    $detector = new SlowQueryDetector(slowThresholdMs: 500);

    expect($detector->detect([]))->toBeEmpty();
});
