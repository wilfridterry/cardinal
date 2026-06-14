<?php

namespace Cardinal\Tests\Unit\Detection;

use Cardinal\Analysis\SchemaInspector;
use Cardinal\Detection\MissingIndexDetector;
use Cardinal\Tests\TestCase;

class MissingIndexDetectorTest extends TestCase
{
    private MissingIndexDetector $detector;
    private SchemaInspector $inspector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inspector = $this->createMock(SchemaInspector::class);
        $this->detector = new MissingIndexDetector($this->inspector, threshold_ms: 500);
    }

    public function test_does_not_fire_for_fast_query(): void
    {
        $this->inspector
            ->expects($this->never())
            ->method('getIndexes');

        $result = $this->detector->analyze(
            'select * from orders where user_id = ?',
            ['orders'],
            duration_ms: 100.0,
        );

        $this->assertNull($result);
    }

    public function test_does_not_fire_when_column_is_indexed(): void
    {
        $this->inspector
            ->method('getIndexes')
            ->with('orders')
            ->willReturn([
                ['name' => 'orders_user_id_index', 'columns' => ['user_id']],
            ]);

        $result = $this->detector->analyze(
            'select * from orders where user_id = ?',
            ['orders'],
            duration_ms: 600.0,
        );

        $this->assertNull($result);
    }

    public function test_fires_when_where_column_has_no_index(): void
    {
        $this->inspector
            ->method('getIndexes')
            ->with('orders')
            ->willReturn([
                ['name' => 'orders_pkey', 'columns' => ['id']],
            ]);

        $result = $this->detector->analyze(
            'select * from orders where status = ?',
            ['orders'],
            duration_ms: 600.0,
        );

        $this->assertNotNull($result);
        $this->assertSame('missing_index', $result['type']);
        $this->assertContains('status', $result['columns']);
    }

    public function test_fires_when_join_column_has_no_index(): void
    {
        $this->inspector
            ->method('getIndexes')
            ->willReturnMap([
                ['orders', [['name' => 'orders_pkey', 'columns' => ['id']]]],
                ['users', [['name' => 'users_pkey', 'columns' => ['id']]]],
            ]);

        $result = $this->detector->analyze(
            'select * from orders inner join users on users.id = orders.customer_id',
            ['orders', 'users'],
            duration_ms: 700.0,
        );

        $this->assertNotNull($result);
        $this->assertSame('missing_index', $result['type']);
        $this->assertContains('customer_id', $result['columns']);
    }

    public function test_fires_for_composite_where_without_covering_index(): void
    {
        $this->inspector
            ->method('getIndexes')
            ->with('orders')
            ->willReturn([
                ['name' => 'orders_pkey', 'columns' => ['id']],
                ['name' => 'orders_user_id_index', 'columns' => ['user_id']],
            ]);

        $result = $this->detector->analyze(
            'select * from orders where user_id = ? and status = ?',
            ['orders'],
            duration_ms: 600.0,
        );

        $this->assertNotNull($result);
        $this->assertContains('status', $result['columns']);
    }

    public function test_does_not_fire_when_all_columns_are_indexed(): void
    {
        $this->inspector
            ->method('getIndexes')
            ->with('orders')
            ->willReturn([
                ['name' => 'orders_pkey', 'columns' => ['id']],
                ['name' => 'orders_user_status', 'columns' => ['user_id', 'status']],
            ]);

        $result = $this->detector->analyze(
            'select * from orders where user_id = ? and status = ?',
            ['orders'],
            duration_ms: 600.0,
        );

        $this->assertNull($result);
    }

    public function test_extracts_unindexed_columns_from_result(): void
    {
        $this->inspector
            ->method('getIndexes')
            ->with('products')
            ->willReturn([
                ['name' => 'products_pkey', 'columns' => ['id']],
            ]);

        $result = $this->detector->analyze(
            'select * from products where category_id = ? and price = ?',
            ['products'],
            duration_ms: 600.0,
        );

        $this->assertNotNull($result);
        $this->assertCount(2, $result['columns']);
        $this->assertContains('category_id', $result['columns']);
        $this->assertContains('price', $result['columns']);
    }

    public function test_skips_tables_with_no_where_columns(): void
    {
        $this->inspector
            ->method('getIndexes')
            ->willReturn([]);

        $result = $this->detector->analyze(
            'select count(*) from orders',
            ['orders'],
            duration_ms: 600.0,
        );

        $this->assertNull($result);
    }
}
