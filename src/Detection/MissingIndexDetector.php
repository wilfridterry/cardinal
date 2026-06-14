<?php

namespace Cardinal\Detection;

use Cardinal\Analysis\SchemaInspector;

class MissingIndexDetector implements DetectorInterface
{
    public function __construct(
        private readonly SchemaInspector $inspector,
        private readonly int $threshold_ms = 500,
    ) {}

    public function detect(array $buffer): array
    {
        $issues = [];

        foreach ($buffer as $entry) {
            if ($entry['max_ms'] < $this->threshold_ms) {
                continue;
            }

            $result = $this->analyze($entry['template'], $entry['tables'] ?? [], $entry['max_ms']);

            if ($result !== null) {
                $issues[] = [
                    'type'     => 'missing_index',
                    'hash'     => $entry['hash'],
                    'template' => $entry['template'],
                    'payload'  => $result,
                ];
            }
        }

        return $issues;
    }

    public function analyze(string $template, array $tables, float $duration_ms): ?array
    {
        if ($duration_ms < $this->threshold_ms) {
            return null;
        }

        $candidates = $this->extractCandidateColumns($template);

        if (empty($candidates)) {
            return null;
        }

        $unindexed = [];

        foreach ($tables as $table) {
            $table = $this->stripAlias($table);
            $indexes = $this->inspector->getIndexes($table);
            $indexedCols = $this->getIndexedColumns($indexes);

            foreach ($candidates as $col) {
                $col = $this->stripTablePrefix($col);
                if (!in_array($col, $indexedCols, true) && !in_array($col, $unindexed, true)) {
                    $unindexed[] = $col;
                }
            }
        }

        if (empty($unindexed)) {
            return null;
        }

        return [
            'type'    => 'missing_index',
            'columns' => $unindexed,
            'tables'  => $tables,
        ];
    }

    private function extractCandidateColumns(string $template): array
    {
        $columns = [];

        if (preg_match_all('/\bwhere\b(.+?)(?:\border\b|\bgroup\b|\blimit\b|\bhaving\b|$)/is', $template, $whereMatches)) {
            foreach ($whereMatches[1] as $clause) {
                if (preg_match_all('/(\w+(?:\.\w+)?)\s*(?:=|<|>|<=|>=|<>|!=)\s*\?/', $clause, $m)) {
                    array_push($columns, ...$m[1]);
                }
            }
        }

        if (preg_match_all('/\bjoin\b.+?\bon\b\s+(\w+(?:\.\w+)?)\s*=\s*(\w+(?:\.\w+)?)/is', $template, $joinMatches)) {
            foreach ([$joinMatches[1], $joinMatches[2]] as $group) {
                foreach ($group as $col) {
                    if (str_contains($col, '.')) {
                        [, $colName] = explode('.', $col, 2);
                        if (!in_array($colName, $columns, true)) {
                            $columns[] = $colName;
                        }
                    }
                }
            }
        }

        return array_values(array_unique($columns));
    }

    private function getIndexedColumns(array $indexes): array
    {
        $cols = [];
        foreach ($indexes as $index) {
            foreach ($index['columns'] as $col) {
                if (!in_array($col, $cols, true)) {
                    $cols[] = $col;
                }
            }
        }
        return $cols;
    }

    private function stripAlias(string $table): string
    {
        return preg_replace('/\s+as\s+\w+$/i', '', trim($table));
    }

    private function stripTablePrefix(string $column): string
    {
        if (str_contains($column, '.')) {
            [, $col] = explode('.', $column, 2);
            return $col;
        }
        return $column;
    }
}
