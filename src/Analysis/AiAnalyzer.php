<?php

declare(strict_types=1);

namespace Cardinal\Analysis;

use Illuminate\Support\Facades\Http;
use Throwable;

class AiAnalyzer
{
    private array $cache = [];

    public function __construct(private array $config)
    {
    }

    public function analyze(string $template, array $explainJson, string $ddl): array
    {
        $config = !empty($this->config['api_key'])
            ? $this->config
            : (config('cardinal.ai') ?? []);

        if (empty($config['api_key']) || empty($config['provider'])) {
            return [];
        }

        $this->config = $config;

        $promptHash = sha1($template . json_encode($explainJson) . $ddl);

        if (isset($this->cache[$promptHash])) {
            return $this->cache[$promptHash];
        }

        $result = match ($this->config['provider']) {
            'anthropic' => $this->callAnthropic($template, $explainJson, $ddl),
            'openai'    => $this->callOpenAi($template, $explainJson, $ddl),
            default     => [],
        };

        if (!empty($result)) {
            $this->cache[$promptHash] = $result;
        }

        return $result;
    }

    private function callAnthropic(string $template, array $explainJson, string $ddl): array
    {
        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->config['api_key'],
                'anthropic-version' => '2023-06-01',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model'      => $this->config['model'] ?? 'claude-sonnet-4-6',
                'max_tokens' => 1024,
                'messages'   => [
                    ['role' => 'user', 'content' => $this->buildPrompt($template, $explainJson, $ddl)],
                ],
            ]);

            if (!$response->successful()) {
                return [];
            }

            $text = $response->json('content.0.text', '');
            $decoded = json_decode($text, true);

            if (!is_array($decoded)) {
                return [];
            }

            return $decoded;
        } catch (Throwable) {
            return [];
        }
    }

    private function callOpenAi(string $template, array $explainJson, string $ddl): array
    {
        try {
            $response = Http::withToken($this->config['api_key'])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'    => $this->config['model'] ?? 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'user', 'content' => $this->buildPrompt($template, $explainJson, $ddl)],
                    ],
                ]);

            if (!$response->successful()) {
                return [];
            }

            $text = $response->json('choices.0.message.content', '');
            $decoded = json_decode($text, true);

            if (!is_array($decoded)) {
                return [];
            }

            return $decoded;
        } catch (Throwable) {
            return [];
        }
    }

    private function buildPrompt(string $template, array $explainJson, string $ddl): string
    {
        $explain = json_encode($explainJson, JSON_PRETTY_PRINT);

        return <<<PROMPT
You are a database performance expert. Analyze this slow Laravel SQL query and respond with ONLY a JSON object, no markdown, no explanation outside JSON.

SQL template (normalized, no real values):
{$template}

EXPLAIN output:
{$explain}

DDL of involved tables:
{$ddl}

Respond with this exact JSON structure:
{
  "diagnosis": "one sentence describing the problem",
  "root_cause": "technical root cause",
  "fix_migration": "Laravel migration code to fix the issue",
  "fix_eloquent": "Eloquent/query builder change if applicable",
  "expected_impact": "expected performance improvement",
  "confidence": "high|medium|low"
}
PROMPT;
    }
}
