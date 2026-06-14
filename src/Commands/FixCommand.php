<?php

declare(strict_types=1);

namespace Cardinal\Commands;

use Cardinal\Fixes\IndexMigrationGenerator;
use Cardinal\Models\Issue;
use Illuminate\Console\Command;

class FixCommand extends Command
{
    protected $signature = 'cardinal:fix {fingerprint : The fingerprint hash of the issue}';
    protected $description = 'Generate a fix for a specific Cardinal issue';

    public function handle(IndexMigrationGenerator $generator): int
    {
        $issue = Issue::where('fingerprint', $this->argument('fingerprint'))->first();

        if (!$issue) {
            $this->error("Issue with fingerprint [{$this->argument('fingerprint')}] not found.");
            return 1;
        }

        if ($issue->type === 'missing_index') {
            $payload = $issue->payload ?? [];
            $columns = $payload['missing_columns'] ?? [];
            $table   = $payload['table'] ?? 'unknown';

            if (empty($columns)) {
                $this->warn('No missing columns recorded for this issue.');
                return 0;
            }

            $this->info("Generating migration for table [{$table}], columns: " . implode(', ', $columns));
            $this->newLine();
            $this->line($generator->generate($table, $columns));
            return 0;
        }

        if ($issue->type === 'n_plus_one') {
            $location = $issue->payload['location'] ?? 'unknown location';
            $this->info("N+1 detected at: {$location}");
            $this->line('Suggested fix: use eager loading.');
            $this->line("Example: change ->get() to ->with(['relation'])->get()");
            return 0;
        }

        if ($issue->type === 'slow') {
            $this->info("Slow query detected: {$issue->template}");
            $this->line('Run cardinal:analyze ' . $issue->fingerprint . ' for AI-powered fix suggestions.');
            return 0;
        }

        $this->warn("No automatic fix available for type [{$issue->type}].");
        return 0;
    }
}
