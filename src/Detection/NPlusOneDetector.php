<?php

declare(strict_types=1);

namespace Cardinal\Detection;

class NPlusOneDetector implements DetectorInterface
{
    public function __construct(
        private readonly int $threshold = 10,
    ) {}

    public function detect(array $buffer): array
    {
        $issues = [];

        foreach ($buffer as $entry) {
            if ($entry['count'] < $this->threshold) {
                continue;
            }

            if (! $this->hasEqualityPredicate($entry['template'])) {
                continue;
            }

            $issues[] = [
                'type'     => 'n_plus_one',
                'hash'     => $entry['hash'],
                'template' => $entry['template'],
                'payload'  => [
                    'repeats'   => $entry['count'],
                    'total_ms'  => $entry['total_ms'],
                    'threshold' => $this->threshold,
                ],
            ];
        }

        return $issues;
    }

    private function hasEqualityPredicate(string $template): bool
    {
        return (bool) preg_match('/\bwhere\b.+?(?:=\s*\?|\bin\s*\(\?\))/i', $template);
    }
}
