<?php

namespace Cardinal\Tests\Unit\Fixes;

use Cardinal\Fixes\IndexMigrationGenerator;
use Cardinal\Tests\TestCase;

class IndexMigrationGeneratorTest extends TestCase
{
    private IndexMigrationGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new IndexMigrationGenerator();
    }

    public function test_generates_single_column_index_migration(): void
    {
        $migration = $this->generator->generate('orders', ['status']);

        $this->assertStringContainsString("Schema::table('orders'", $migration);
        $this->assertStringContainsString("->index(['status']", $migration);
        $this->assertStringContainsString('dropIndex', $migration);
    }

    public function test_generates_composite_index_migration(): void
    {
        $migration = $this->generator->generate('orders', ['user_id', 'status']);

        $this->assertStringContainsString("->index(['user_id', 'status']", $migration);
    }

    public function test_migration_has_up_and_down_methods(): void
    {
        $migration = $this->generator->generate('orders', ['status']);

        $this->assertStringContainsString('public function up()', $migration);
        $this->assertStringContainsString('public function down()', $migration);
    }

    public function test_index_name_is_unique_per_table_and_columns(): void
    {
        $migration1 = $this->generator->generate('orders', ['status']);
        $migration2 = $this->generator->generate('users', ['email']);

        preg_match("/dropIndex\('(\w+)'\)/", $migration1, $m1);
        preg_match("/dropIndex\('(\w+)'\)/", $migration2, $m2);

        $this->assertNotEmpty($m1[1]);
        $this->assertNotEmpty($m2[1]);
        $this->assertNotEquals($m1[1], $m2[1]);
    }

    public function test_generates_valid_php_syntax(): void
    {
        $migration = $this->generator->generate('products', ['category_id', 'price']);

        $tokens = token_get_all($migration);
        $errors = [];
        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_BAD_CHARACTER) {
                $errors[] = $token;
            }
        }

        $this->assertEmpty($errors);
    }

    public function test_index_name_is_deterministic(): void
    {
        $migration1 = $this->generator->generate('orders', ['user_id', 'status']);
        $migration2 = $this->generator->generate('orders', ['user_id', 'status']);

        $this->assertSame($migration1, $migration2);
    }
}
