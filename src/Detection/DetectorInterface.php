<?php

declare(strict_types=1);

namespace Cardinal\Detection;

interface DetectorInterface
{
    /**
     * @param  array<int, array{hash: string, template: string, count: int, total_ms: float, max_ms: float}>  $buffer
     * @return array<int, array{type: string, hash: string, template: string, payload: array<string, mixed>}>
     */
    public function detect(array $buffer): array;
}
