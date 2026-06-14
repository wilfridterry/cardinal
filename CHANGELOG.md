# Changelog

All notable changes to Cardinal will be documented in this file.

## [Unreleased]

## [0.1.0] - 2024-01-01

### Added
- `QueryFingerprinter` — SQL normalization with privacy invariant (no literals in output)
- `QueryRecorder` — per-request buffer with sample_rate and ignore lists
- `RequestContext` — HTTP / job / console context detection
- `SlowQueryDetector` — fires when max_ms >= slow_threshold_ms
- `NPlusOneDetector` — fires when fingerprint repeats >= n_plus_one_threshold per request
- `MissingIndexDetector` — detects unindexed WHERE/JOIN columns in slow queries
- `SchemaInspector` — reads indexes from MySQL, PostgreSQL, SQLite
- `ExplainRunner` — on-demand EXPLAIN for MySQL, PostgreSQL, SQLite
- `AiAnalyzer` — Claude/OpenAI analysis with prompt caching by hash
- `IndexMigrationGenerator` — generates ready-to-run Laravel migrations
- `EagerLoadAdvisor` — suggests Eloquent eager loading for N+1 issues
- Artisan commands: `cardinal:report`, `cardinal:analyze`, `cardinal:fix`, `cardinal:deploy`
- 97 Pest tests including privacy invariant and CI benchmark
