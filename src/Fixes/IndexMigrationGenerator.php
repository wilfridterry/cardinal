<?php

namespace Cardinal\Fixes;

class IndexMigrationGenerator
{
    public function generate(string $table, array $columns): string
    {
        $indexName = $table . '_' . implode('_', $columns) . '_index';
        $columnsExport = "['" . implode("', '", $columns) . "']";

        $lines = [
            '<?php',
            '',
            'use Illuminate\Database\Migrations\Migration;',
            'use Illuminate\Database\Schema\Blueprint;',
            'use Illuminate\Support\Facades\Schema;',
            '',
            'return new class extends Migration',
            '{',
            '    public function up(): void',
            '    {',
            "        Schema::table('{$table}', function (Blueprint \$table) {",
            "            \$table->index({$columnsExport}, '{$indexName}');",
            '        });',
            '    }',
            '',
            '    public function down(): void',
            '    {',
            "        Schema::table('{$table}', function (Blueprint \$table) {",
            "            \$table->dropIndex('{$indexName}');",
            '        });',
            '    }',
            '};',
        ];

        return implode("\n", $lines) . "\n";
    }
}
