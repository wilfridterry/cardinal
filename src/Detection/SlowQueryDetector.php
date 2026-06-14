<?php

declare(strict_types=1);

namespace Cardinal\Detection;

class SlowQueryDetector implements DetectorInterface
{
    public function __construct(
        private readonly float $slowThresholdMs = 500.0,
    ) {}

    public function detect(array $buffer): array
    {
        $issues = [];

        foreach ($buffer as $entry) {
            if ($entry['max_ms'] >= $this->slowThresholdMs) {
                $issues[] = [
                    'type'     => 'slow',
                    'hash'     => $entry['hash'],
                    'template' => $entry['template'],
                    'payload'  => [
                        'max_ms'       => $entry['max_ms'],
                        'total_ms'     => $entry['total_ms'],
                        'count'        => $entry['count'],
                        'threshold_ms' => $this->slowThresholdMs,
                    ],
                ];
            }
        }

        return $issues;
    }
}
