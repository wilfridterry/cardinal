<?php

namespace Cardinal\Fixes;

class EagerLoadAdvisor
{
    public function suggest(string $template, string $location): string
    {
        preg_match('/from\s+`?(\w+)`?/i', $template, $matches);
        $table = $matches[1] ?? 'models';

        $model = str($table)->singular()->studly()->toString();

        return implode("\n", [
            "// N+1 detected at: {$location}",
            "// Instead of lazy loading, use eager loading:",
            "",
            "// Before (causes N+1):",
            "// \${$table} = {$model}::all();",
            "// foreach (\${$table} as \$item) { \$item->relation; }",
            "",
            "// After (eager load):",
            "// \${$table} = {$model}::with('relation')->get();",
        ]);
    }
}
