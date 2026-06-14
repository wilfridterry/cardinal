<?php

declare(strict_types=1);

namespace Cardinal\Analysis;

use Illuminate\Support\Facades\DB;
use Throwable;

class ExplainRunner
{
    public function explain(string $sql, array $bindings = []): array
    {
        try {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'sqlite') {
                return $this->explainSqlite($sql, $bindings);
            }

            if ($driver === 'pgsql') {
                return $this->explainPgsql($sql, $bindings);
            }

            return $this->explainMysql($sql, $bindings);
        } catch (Throwable) {
            return [];
        }
    }

    private function explainSqlite(string $sql, array $bindings): array
    {
        $rows = DB::select('EXPLAIN ' . $sql, $bindings);
        return array_map(fn($r) => (array) $r, $rows);
    }

    private function explainMysql(string $sql, array $bindings): array
    {
        $rows = DB::select('EXPLAIN FORMAT=JSON ' . $sql, $bindings);
        if (empty($rows)) {
            return [];
        }
        $json = (array) $rows[0];
        $raw = reset($json);
        $decoded = json_decode($raw, true);
        return $decoded ?? [];
    }

    private function explainPgsql(string $sql, array $bindings): array
    {
        $rows = DB::select('EXPLAIN (FORMAT JSON) ' . $sql, $bindings);
        if (empty($rows)) {
            return [];
        }
        $json = (array) $rows[0];
        $raw = reset($json);
        $decoded = json_decode($raw, true);
        return $decoded ?? [];
    }
}
