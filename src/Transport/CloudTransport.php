<?php

namespace Cardinal\Transport;

use Illuminate\Support\Facades\Http;

class CloudTransport implements TransportInterface
{
    public function __construct(
        private string $token,
        private string $endpoint,
    ) {}

    public function send(array $metrics, array $issues): void
    {
        if (empty($metrics) && empty($issues)) {
            return;
        }

        Http::withToken($this->token)
            ->timeout(5)
            ->retry(2, 500)
            ->post($this->endpoint . '/ingest', [
                'agent'   => 'cardinal-laravel/0.2.0',
                'window'  => [
                    'from' => now()->subMinutes(1)->toIso8601String(),
                    'to'   => now()->toIso8601String(),
                ],
                'metrics' => $metrics,
                'issues'  => $issues,
            ]);
    }
}
