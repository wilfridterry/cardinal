<?php

declare(strict_types=1);

namespace Cardinal\Commands;

use Illuminate\Console\Command;

class DeployCommand extends Command
{
    protected $signature = 'cardinal:deploy
        {--sha= : Git commit SHA}
        {--branch= : Git branch name}
        {--at= : Deployment timestamp (ISO 8601)}';

    protected $description = 'Ping Cardinal Cloud with a deploy event';

    public function handle(): int
    {
        $cloudEnabled = config('cardinal.cloud.enabled', false);

        if (!$cloudEnabled) {
            $this->warn('Cardinal Cloud is not enabled. Set CARDINAL_CLOUD=true and CARDINAL_TOKEN.');
            return 0;
        }

        $token    = config('cardinal.cloud.token');
        $endpoint = config('cardinal.cloud.endpoint');

        if (!$token) {
            $this->error('CARDINAL_TOKEN is not set.');
            return 1;
        }

        $sha      = $this->option('sha') ?? exec('git rev-parse HEAD 2>/dev/null') ?: 'unknown';
        $branch   = $this->option('branch') ?? exec('git rev-parse --abbrev-ref HEAD 2>/dev/null') ?: 'unknown';
        $deployedAt = $this->option('at') ?? now()->toIso8601String();

        $this->info("Pinging Cardinal Cloud: sha={$sha} branch={$branch}");

        $response = \Illuminate\Support\Facades\Http::withToken($token)
            ->post($endpoint . '/deploys', [
                'sha'         => $sha,
                'branch'      => $branch,
                'deployed_at' => $deployedAt,
            ]);

        if ($response->successful()) {
            $this->info('Deploy registered successfully.');
            return 0;
        }

        $this->error('Failed to register deploy: ' . $response->status());
        return 1;
    }
}
