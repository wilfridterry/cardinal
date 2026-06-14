<?php

declare(strict_types=1);

namespace Cardinal\Recording;

final class RequestContext
{
    public function __construct(
        public readonly string $type,
        public readonly string $name,
    ) {}

    public static function forRoute(string $method, string $uri): self
    {
        return new self('http', trim($method.' '.$uri));
    }

    public static function forJob(string $jobClass): self
    {
        return new self('job', $jobClass);
    }

    public static function forCommand(string $command): self
    {
        return new self('console', $command);
    }

    public static function unknown(): self
    {
        return new self('unknown', 'unknown');
    }

    /** @return array{type: string, name: string} */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
        ];
    }
}
