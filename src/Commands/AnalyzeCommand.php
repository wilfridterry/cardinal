<?php

declare(strict_types=1);

namespace Cardinal\Commands;

use Cardinal\Analysis\AiAnalyzer;
use Cardinal\Analysis\ExplainRunner;
use Cardinal\Models\Issue;
use Illuminate\Console\Command;

class AnalyzeCommand extends Command
{
    protected $signature = 'cardinal:analyze {fingerprint : The fingerprint hash of the issue}';
    protected $description = 'Run AI analysis on a specific Cardinal issue';

    public function handle(AiAnalyzer $analyzer, ExplainRunner $explainer): int
    {
        $issue = Issue::where('fingerprint', $this->argument('fingerprint'))->first();

        if (!$issue) {
            $this->error("Issue with fingerprint [{$this->argument('fingerprint')}] not found.");
            return 1;
        }

        $this->info("Analyzing: {$issue->template}");
        $this->newLine();

        $explain = $explainer->explain($issue->template, []);
        $ddl = '';

        $result = $analyzer->analyze($issue->template, $explain, $ddl);

        if (empty($result)) {
            $this->warn('No AI analysis available. Check your CARDINAL_AI_KEY configuration.');
            return 0;
        }

        $this->line('<fg=yellow>Diagnosis:</> ' . ($result['diagnosis'] ?? ''));
        $this->line('<fg=yellow>Root cause:</> ' . ($result['root_cause'] ?? ''));
        $this->line('<fg=yellow>Expected impact:</> ' . ($result['expected_impact'] ?? ''));
        $this->line('<fg=yellow>Confidence:</> ' . ($result['confidence'] ?? ''));
        $this->newLine();
        $this->line('<fg=green>Fix (migration):</>');
        $this->line($result['fix_migration'] ?? 'N/A');
        $this->newLine();
        $this->line('<fg=green>Fix (Eloquent):</>');
        $this->line($result['fix_eloquent'] ?? 'N/A');

        return 0;
    }
}
