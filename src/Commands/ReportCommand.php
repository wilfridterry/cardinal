<?php

declare(strict_types=1);

namespace Cardinal\Commands;

use Cardinal\Models\Issue;
use Illuminate\Console\Command;

class ReportCommand extends Command
{
    protected $signature = 'cardinal:report {--type= : Filter by type: slow, n_plus_one, missing_index}';
    protected $description = 'Show all Cardinal issues detected in this application';

    public function handle(): int
    {
        $query = Issue::query()->orderByDesc('last_seen_at');

        if ($type = $this->option('type')) {
            $query->where('type', $type);
        }

        $issues = $query->get();

        if ($issues->isEmpty()) {
            $this->info('No issues found.');
            return 0;
        }

        $this->table(
            ['Fingerprint', 'Type', 'Max ms', 'Count', 'Last seen'],
            $issues->map(fn(Issue $i) => [
                substr($i->fingerprint, 0, 8),
                $i->type,
                $i->max_ms,
                $i->count,
                $i->last_seen_at?->diffForHumans(),
            ])
        );

        return 0;
    }
}
