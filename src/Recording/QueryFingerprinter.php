<?php

declare(strict_types=1);

namespace Cardinal\Recording;

class QueryFingerprinter
{
    public function fingerprint(string $sql): string
    {
        return sha1($this->template($sql));
    }

    public function template(string $sql): string
    {
        $sql = $this->replaceStringLiterals($sql);
        $sql = $this->replaceNumericLiterals($sql);
        $sql = $this->replaceBooleanLiterals($sql);
        $sql = $this->collapseInLists($sql);
        $sql = $this->normaliseWhitespace($sql);
        $sql = $this->lowercaseKeywords($sql);

        return trim($sql);
    }

    private function replaceStringLiterals(string $sql): string
    {
        $sql = preg_replace("/'(?:[^']|'')*'/", '?', $sql);
        $sql = preg_replace('/"(?:[^"]|"")*"/', '?', $sql);

        return $sql;
    }

    private function replaceNumericLiterals(string $sql): string
    {
        return preg_replace('/(?<![\w.])-?\d+(?:\.\d+)?\b/', '?', $sql);
    }

    private function replaceBooleanLiterals(string $sql): string
    {
        return preg_replace('/\b(?:true|false)\b/i', '?', $sql);
    }

    private function collapseInLists(string $sql): string
    {
        return preg_replace('/\bin\s*\(\s*\?(?:\s*,\s*\?)*\s*\)/i', 'in (?)', $sql);
    }

    private function normaliseWhitespace(string $sql): string
    {
        return preg_replace('/\s+/', ' ', $sql);
    }

    private function lowercaseKeywords(string $sql): string
    {
        return strtolower($sql);
    }
}
