<?php

declare(strict_types=1);

namespace Cardinal\Recording;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

class QueryRecorder
{
    private QueryFingerprinter $fingerprinter;

    private RequestContext $context;

    /** @var array<string, array{hash: string, template: string, count: int, total_ms: float, max_ms: float}> */
    private array $buffer = [];

    private ?bool $sampled = null;

    public function __construct(
        private array $config,
        ?QueryFingerprinter $fingerprinter = null,
    ) {
        $this->fingerprinter = $fingerprinter ?? new QueryFingerprinter();
        $this->context = RequestContext::unknown();
    }

    public function listen(): void
    {
        DB::listen(function (QueryExecuted $event): void {
            if (! $this->shouldSample()) {
                return;
            }

            $this->record($event->sql, (float) $event->time);
        });
    }

    public function record(string $sql, float $timeMs): void
    {
        if ($this->isIgnored($sql)) {
            return;
        }

        $template = $this->fingerprinter->template($sql);
        $hash = sha1($template);

        if (! isset($this->buffer[$hash])) {
            $this->buffer[$hash] = [
                'hash'     => $hash,
                'template' => $template,
                'count'    => 0,
                'total_ms' => 0.0,
                'max_ms'   => 0.0,
            ];
        }

        $this->buffer[$hash]['count']++;
        $this->buffer[$hash]['total_ms'] += $timeMs;
        $this->buffer[$hash]['max_ms'] = max($this->buffer[$hash]['max_ms'], $timeMs);
    }

    public function shouldSample(): bool
    {
        if ($this->sampled !== null) {
            return $this->sampled;
        }

        $rate = (float) ($this->config['sample_rate'] ?? 1.0);

        if ($rate >= 1.0) {
            return $this->sampled = true;
        }

        if ($rate <= 0.0) {
            return $this->sampled = false;
        }

        return $this->sampled = (mt_rand() / mt_getrandmax()) < $rate;
    }

    private function isIgnored(string $sql): bool
    {
        $patterns = $this->config['ignore']['tables'] ?? [];

        if ($patterns === []) {
            return false;
        }

        $lower = strtolower($sql);

        foreach ($patterns as $pattern) {
            if ($this->matchesTable($lower, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    private function matchesTable(string $sql, string $pattern): bool
    {
        if (str_ends_with($pattern, '*')) {
            $prefix = rtrim($pattern, '*');

            return (bool) preg_match('/\b'.preg_quote($prefix, '/').'\w*\b/', $sql);
        }

        return (bool) preg_match('/\b'.preg_quote($pattern, '/').'\b/', $sql);
    }

    public function setContext(RequestContext $context): void
    {
        $this->context = $context;
    }

    public function context(): RequestContext
    {
        return $this->context;
    }

    /** @return array<int, array{hash: string, template: string, count: int, total_ms: float, max_ms: float}> */
    public function buffer(): array
    {
        return array_values($this->buffer);
    }

    public function reset(): void
    {
        $this->buffer  = [];
        $this->sampled = null;
        $this->context = RequestContext::unknown();
    }
}
