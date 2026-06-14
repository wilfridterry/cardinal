<?php

declare(strict_types=1);

use Cardinal\Recording\QueryFingerprinter;

beforeEach(function () {
    $this->fp = new QueryFingerprinter();
});

/*
|--------------------------------------------------------------------------
| Template normalisation — the heart of the system.
| ≥30 raw SQL → expected template pairs.
|--------------------------------------------------------------------------
*/
dataset('sql_templates', [
    // integers
    ['select * from users where id = 5', 'select * from users where id = ?'],
    ['select * from users where id = 12345', 'select * from users where id = ?'],
    ['select * from orders where total > 99.95', 'select * from orders where total > ?'],
    ['select * from orders where total > -10', 'select * from orders where total > ?'],

    // single-quoted strings
    ["select * from users where email = 'a@b.com'", 'select * from users where email = ?'],
    ["select * from users where name = 'O''Brien'", 'select * from users where name = ?'],
    ['select * from users where name = "double"', 'select * from users where name = ?'],

    // IN lists collapse regardless of length (CRITICAL)
    ['select * from users where id in (1, 2, 3)', 'select * from users where id in (?)'],
    ['select * from users where id in (1)', 'select * from users where id in (?)'],
    ['select * from users where id in (1,2,3,4,5,6,7,8,9,10)', 'select * from users where id in (?)'],
    ["select * from users where status in ('a', 'b', 'c')", 'select * from users where status in (?)'],

    // existing placeholders are preserved as ?
    ['select * from users where id = ?', 'select * from users where id = ?'],
    ['select * from users where id in (?, ?, ?)', 'select * from users where id in (?)'],

    // whitespace normalisation
    ["select   *   from    users\nwhere id = 5", 'select * from users where id = ?'],
    ["select *\tfrom users\t where id = 5", 'select * from users where id = ?'],

    // keyword case normalisation
    ['SELECT * FROM users WHERE id = 5', 'select * from users where id = ?'],
    ['Select * From Users Where Id = 5', 'select * from users where id = ?'],

    // LIKE literals
    ["select * from users where name like 'john%'", 'select * from users where name like ?'],
    ["select * from users where name like '%smith'", 'select * from users where name like ?'],
    ['select * from users where name like ?', 'select * from users where name like ?'],

    // subqueries — literals inside must also be replaced
    ['select * from a where id in (select id from b where x = 5)', 'select * from a where id in (select id from b where x = ?)'],
    ["select * from a where id = (select max(id) from b where t = 'x')", 'select * from a where id = (select max(id) from b where t = ?)'],

    // multiple conditions
    ["select * from users where id = 5 and email = 'a@b.com'", 'select * from users where id = ? and email = ?'],
    ['update users set name = "x", age = 30 where id = 5', 'update users set name = ?, age = ? where id = ?'],
    ['insert into users (name, age) values ("x", 30)', 'insert into users (name, age) values (?, ?)'],

    // postgres JSON operators must NOT be mangled
    ["select data->>'name' from users where id = 5", 'select data->>? from users where id = ?'],
    ["select data->'meta'->>'k' from users where id = 5", 'select data->?->>? from users where id = ?'],

    // limit / offset numbers
    ['select * from users limit 10 offset 20', 'select * from users limit ? offset ?'],

    // boolean / null literals are kept (not values)
    ['select * from users where active = true', 'select * from users where active = ?'],
    ['select * from users where deleted_at is null', 'select * from users where deleted_at is null'],

    // dates as strings
    ["select * from logs where created_at > '2024-01-01 00:00:00'", 'select * from logs where created_at > ?'],

    // between
    ['select * from orders where total between 10 and 100', 'select * from orders where total between ? and ?'],
]);

it('normalises SQL into a stable template', function (string $raw, string $expected) {
    expect($this->fp->template($raw))->toBe($expected);
})->with('sql_templates');

/*
|--------------------------------------------------------------------------
| Fingerprint stability & identity
|--------------------------------------------------------------------------
*/
it('produces identical fingerprints for queries differing only in literals', function () {
    $a = $this->fp->fingerprint('select * from users where id = 1');
    $b = $this->fp->fingerprint('select * from users where id = 99999');

    expect($a)->toBe($b);
});

it('produces identical fingerprints for IN lists of different lengths', function () {
    $a = $this->fp->fingerprint('select * from users where id in (1, 2, 3)');
    $b = $this->fp->fingerprint('select * from users where id in (7)');

    expect($a)->toBe($b);
});

it('produces different fingerprints for structurally different queries', function () {
    $a = $this->fp->fingerprint('select * from users where id = 1');
    $b = $this->fp->fingerprint('select * from orders where id = 1');

    expect($a)->not->toBe($b);
});

it('returns a sha1 hex fingerprint', function () {
    expect($this->fp->fingerprint('select * from users where id = 1'))
        ->toMatch('/^[0-9a-f]{40}$/');
});

/*
|--------------------------------------------------------------------------
| PRIVACY INVARIANT — risk #2. The template must NEVER leak literals.
|--------------------------------------------------------------------------
*/
dataset('queries_with_literals', [
    "select * from users where email = 'secret@example.com'",
    'select * from users where id = 4815162342',
    "select * from cards where number = '4111111111111111'",
    "select * from users where api_key = 'sk-live-abcdef123456'",
    "insert into logs (msg) values ('user 12345 did a thing')",
    "select * from users where name like 'Alice%' and ssn = '123-45-6789'",
    'update accounts set balance = 1000000 where id = 42',
]);

it('never leaks literal values into the template', function (string $raw) {
    $template = $this->fp->template($raw);

    // No digits should survive except inside identifiers we explicitly allow.
    // Our templates only ever contain '?' for values, so assert there are
    // no standalone numeric literals and no quoted strings.
    expect($template)
        ->not->toContain("'")
        ->not->toContain('"')
        ->not->toMatch('/\b\d{2,}\b/'); // no multi-digit numbers leak through
})->with('queries_with_literals');

it('strips a specific secret string completely', function () {
    $template = $this->fp->template("select * from users where token = 'sk-live-TOPSECRET'");

    expect($template)->not->toContain('TOPSECRET');
    expect($template)->not->toContain('sk-live');
});
