<?php

namespace Cardinal\Tests\Unit\Analysis;

use Cardinal\Analysis\ExplainRunner;
use Illuminate\Support\Facades\DB;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertIsArray;

it('runs explain and parses json output', function () {
    DB::statement('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
    DB::insert('insert into users (id, name) values (?, ?)', [1, 'Alice']);

    $runner = new ExplainRunner();
    $result = $runner->explain('SELECT * FROM users WHERE id = ?', [1]);

    assertIsArray($result);
    expect($result)->not->toBeEmpty();
    expect($result[0])->toHaveKey('opcode');
});

it('handles invalid sql gracefully', function () {
    $runner = new ExplainRunner();
    $result = $runner->explain('SELECT * FROM nonexistent_table', []);

    assertEquals([], $result);
});

it('extracts table list from explain', function () {
    DB::statement('CREATE TABLE orders (id INTEGER PRIMARY KEY, user_id INTEGER)');

    $runner = new ExplainRunner();
    $result = $runner->explain('SELECT * FROM orders WHERE user_id = ?', [1]);

    assertIsArray($result);
});
