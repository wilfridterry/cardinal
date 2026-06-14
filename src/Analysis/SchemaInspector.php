<?php

namespace Cardinal\Analysis;

use Illuminate\Support\Facades\DB;
use Throwable;

class SchemaInspector
{
    private array $columnCache = [];
    private array $indexCache = [];

    public function getColumns(string $table): array
    {
        if (isset($this->columnCache[$table])) {
            return $this->columnCache[$table];
        }

        try {
            $driver = DB::getDriverName();
            $columns = match ($driver) {
                'mysql', 'mariadb' => $this->mysqlColumns($table),
                'pgsql' => $this->pgsqlColumns($table),
                'sqlite' => $this->sqliteColumns($table),
                default => [],
            };
        } catch (Throwable) {
            $columns = [];
        }

        return $this->columnCache[$table] = $columns;
    }

    public function getIndexes(string $table): array
    {
        if (isset($this->indexCache[$table])) {
            return $this->indexCache[$table];
        }

        try {
            $driver = DB::getDriverName();
            $indexes = match ($driver) {
                'mysql', 'mariadb' => $this->mysqlIndexes($table),
                'pgsql' => $this->pgsqlIndexes($table),
                'sqlite' => $this->sqliteIndexes($table),
                default => [],
            };
        } catch (Throwable) {
            $indexes = [];
        }

        return $this->indexCache[$table] = $indexes;
    }

    private function mysqlColumns(string $table): array
    {
        $rows = DB::select('SHOW COLUMNS FROM `' . $table . '`');
        return array_column(array_map(fn($r) => (array) $r, $rows), 'Field');
    }

    private function mysqlIndexes(string $table): array
    {
        $rows = DB::select('SHOW INDEX FROM `' . $table . '`');
        $groups = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $name = $row['Key_name'];
            $groups[$name]['name'] = $name;
            $groups[$name]['columns'][] = $row['Column_name'];
        }
        return array_values($groups);
    }

    private function pgsqlColumns(string $table): array
    {
        $rows = DB::select(
            "SELECT column_name FROM information_schema.columns WHERE table_name = ? AND table_schema = 'public'",
            [$table]
        );
        return array_column(array_map(fn($r) => (array) $r, $rows), 'column_name');
    }

    private function pgsqlIndexes(string $table): array
    {
        $rows = DB::select(
            "SELECT indexname AS name, indexdef AS def FROM pg_indexes WHERE tablename = ? AND schemaname = 'public'",
            [$table]
        );

        $indexes = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            preg_match('/\((.+)\)/', $row['def'], $m);
            $columns = isset($m[1])
                ? array_map('trim', explode(',', $m[1]))
                : [];
            $indexes[] = ['name' => $row['name'], 'columns' => $columns];
        }
        return $indexes;
    }

    private function sqliteColumns(string $table): array
    {
        $rows = DB::select('PRAGMA table_info(' . $table . ')');
        if (empty($rows)) {
            return [];
        }
        return array_column(array_map(fn($r) => (array) $r, $rows), 'name');
    }

    private function sqliteIndexes(string $table): array
    {
        $rows = DB::select('PRAGMA index_list(' . $table . ')');
        $indexes = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $name = $row['name'];
            $infoRows = DB::select('PRAGMA index_info(' . $name . ')');
            $columns = array_column(array_map(fn($r) => (array) $r, $infoRows), 'name');

            $entry = ['name' => $name, 'columns' => $columns];

            if (str_starts_with($name, 'sqlite_autoindex_')) {
                array_unshift($indexes, $entry);
            } else {
                $indexes[] = $entry;
            }
        }

        $pkCols = $this->sqlitePrimaryKeyColumns($table);
        if (!empty($pkCols)) {
            array_unshift($indexes, ['name' => $table . '_pkey', 'columns' => $pkCols]);
        }

        return $indexes;
    }

    private function sqlitePrimaryKeyColumns(string $table): array
    {
        $rows = DB::select('PRAGMA table_info(' . $table . ')');
        $pk = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            if ($row['pk'] > 0) {
                $pk[$row['pk']] = $row['name'];
            }
        }
        ksort($pk);
        return array_values($pk);
    }
}
