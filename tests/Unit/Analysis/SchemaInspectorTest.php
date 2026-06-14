<?php

namespace Cardinal\Tests\Unit\Analysis;

use Illuminate\Support\Facades\DB;
use Cardinal\Analysis\SchemaInspector;
use Cardinal\Tests\TestCase;

class SchemaInspectorTest extends TestCase
{
    private SchemaInspector $inspector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inspector = new SchemaInspector();
    }

    public function test_get_columns_from_table_mysql(): void
    {
        $this->useConnection('mysql');
        
        $columns = $this->inspector->getColumns('users');
        
        $this->assertNotEmpty($columns);
        $this->assertContains('id', $columns);
        $this->assertContains('email', $columns);
    }

    public function test_get_columns_from_table_postgres(): void
    {
        $this->useConnection('pgsql');
        
        $columns = $this->inspector->getColumns('users');
        
        $this->assertNotEmpty($columns);
        $this->assertContains('id', $columns);
        $this->assertContains('email', $columns);
    }

    public function test_get_indexes_from_table_mysql(): void
    {
        $this->useConnection('mysql');
        
        $indexes = $this->inspector->getIndexes('users');
        
        $this->assertNotEmpty($indexes);
        $this->assertTrue(
            collect($indexes)->pluck('columns')->flatten()->contains('id')
        );
    }

    public function test_get_indexes_from_table_postgres(): void
    {
        $this->useConnection('pgsql');
        
        $indexes = $this->inspector->getIndexes('users');
        
        $this->assertNotEmpty($indexes);
        $this->assertTrue(
            collect($indexes)->pluck('columns')->flatten()->contains('id')
        );
    }

    public function test_get_columns_returns_empty_for_nonexistent_table(): void
    {
        $columns = $this->inspector->getColumns('nonexistent_table_xyz');
        
        $this->assertEmpty($columns);
    }

    public function test_get_indexes_returns_empty_for_nonexistent_table(): void
    {
        $indexes = $this->inspector->getIndexes('nonexistent_table_xyz');
        
        $this->assertEmpty($indexes);
    }

    public function test_index_structure_contains_name_and_columns(): void
    {
        $this->useConnection('mysql');
        
        $indexes = $this->inspector->getIndexes('users');
        
        foreach ($indexes as $index) {
            $this->assertArrayHasKey('name', $index);
            $this->assertArrayHasKey('columns', $index);
            $this->assertIsArray($index['columns']);
        }
    }

    private function useConnection(string $driver): void
    {
        if ($driver === 'pgsql' && config('database.default') !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL not configured');
        }
        if ($driver === 'mysql' && config('database.default') !== 'mysql') {
            $this->markTestSkipped('MySQL not configured');
        }
    }
}
