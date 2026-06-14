<?php

declare(strict_types=1);

use Cardinal\Recording\RequestContext;

it('captures an http route context', function () {
    $ctx = RequestContext::forRoute('GET', 'orders/{order}');

    expect($ctx->type)->toBe('http')
        ->and($ctx->name)->toBe('GET orders/{order}');
});

it('captures a job context', function () {
    $ctx = RequestContext::forJob('App\\Jobs\\ProcessOrder');

    expect($ctx->type)->toBe('job')
        ->and($ctx->name)->toBe('App\\Jobs\\ProcessOrder');
});

it('captures a console command context', function () {
    $ctx = RequestContext::forCommand('queue:work');

    expect($ctx->type)->toBe('console')
        ->and($ctx->name)->toBe('queue:work');
});

it('falls back to an unknown context', function () {
    $ctx = RequestContext::unknown();

    expect($ctx->type)->toBe('unknown')
        ->and($ctx->name)->toBe('unknown');
});

it('is serialisable to an array', function () {
    $ctx = RequestContext::forJob('App\\Jobs\\Foo');

    expect($ctx->toArray())->toBe([
        'type' => 'job',
        'name' => 'App\\Jobs\\Foo',
    ]);
});
