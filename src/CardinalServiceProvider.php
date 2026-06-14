<?php

declare(strict_types=1);

namespace Cardinal;

use Illuminate\Support\ServiceProvider;
use Cardinal\Analysis\AiAnalyzer;
use Cardinal\Analysis\ExplainRunner;
use Cardinal\Commands\AnalyzeCommand;
use Cardinal\Commands\DeployCommand;
use Cardinal\Commands\FixCommand;
use Cardinal\Commands\ReportCommand;
use Cardinal\Detection\NPlusOneDetector;
use Cardinal\Detection\SlowQueryDetector;
use Cardinal\Fixes\IndexMigrationGenerator;
use Cardinal\Models\Issue;
use Cardinal\Recording\QueryRecorder;

class CardinalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cardinal.php', 'cardinal');

        $this->app->singleton(QueryRecorder::class, function ($app) {
            return new QueryRecorder(config: $app['config']['cardinal']);
        });

        $this->app->singleton(AiAnalyzer::class, function ($app) {
            return new AiAnalyzer($app['config']['cardinal']['ai'] ?? []);
        });

        $this->app->singleton(ExplainRunner::class, fn() => new ExplainRunner());

        $this->app->singleton(IndexMigrationGenerator::class, fn() => new IndexMigrationGenerator());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/cardinal.php' => config_path('cardinal.php'),
            ], 'cardinal-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'cardinal-migrations');

            $this->commands([
                ReportCommand::class,
                AnalyzeCommand::class,
                FixCommand::class,
                DeployCommand::class,
            ]);
        }

        if (! config('cardinal.enabled', true)) {
            return;
        }

        $recorder = $this->app->make(QueryRecorder::class);
        $recorder->listen();

        $this->app->terminating(function () use ($recorder): void {
            $this->flush($recorder);
        });
    }

    private function flush(QueryRecorder $recorder): void
    {
        $buffer = $recorder->buffer();

        if ($buffer === []) {
            return;
        }

        $config  = config('cardinal');
        $context = $recorder->context();

        $detectors = [
            new SlowQueryDetector((float) ($config['slow_threshold_ms'] ?? 500)),
            new NPlusOneDetector((int) ($config['n_plus_one_threshold'] ?? 10)),
        ];

        $now = now();

        foreach ($detectors as $detector) {
            foreach ($detector->detect($buffer) as $issue) {
                Issue::updateOrCreate(
                    [
                        'type'        => $issue['type'],
                        'fingerprint' => $issue['hash'],
                    ],
                    [
                        'template'     => $issue['template'],
                        'context_type' => $context->type,
                        'context_name' => $context->name,
                        'max_ms'       => $issue['payload']['max_ms'] ?? $issue['payload']['total_ms'] ?? 0,
                        'total_ms'     => $issue['payload']['total_ms'] ?? 0,
                        'count'        => $issue['payload']['count'] ?? $issue['payload']['repeats'] ?? 0,
                        'payload'      => $issue['payload'],
                        'last_seen_at' => $now,
                    ]
                );
            }
        }

        $recorder->reset();
    }
}
