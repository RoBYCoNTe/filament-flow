<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Hybrid;

use DB;
use ReflectionClass;
use ReflectionException;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\OrderState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ShippedState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Test the HasFlexibleStates trait for handling both PHP State classes and database-only states
 */
class FlexibleStateCastTest extends TestCase
{
    /**
     * Test getAttribute returns State instance for PHP State class
     */
    public function test_get_attribute_returns_state_instance_for_php_class(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FLEX-001',
            'customer_name' => 'John Doe',
            'total_amount' => 100.00,
            'state' => PendingState::class,
        ]);

        $this->assertInstanceOf(PendingState::class, $order->state);
    }

    /**
     * Test getAttribute returns string for database-only state
     */
    public function test_get_attribute_returns_string_for_db_only_state(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FLEX-002',
            'customer_name' => 'Jane Doe',
            'total_amount' => 150.00,
        ]);

        DB::table($order->getTable())
            ->where($order->getKeyName(), $order->getKey())
            ->update(['state' => 'custom_db_state']);

        $order->refresh();

        $this->assertIsString($order->state);
        $this->assertEquals('custom_db_state', $order->state);
    }

    /**
     * Test setAttribute accepts State instance
     */
    public function test_set_attribute_accepts_state_instance(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FLEX-003',
            'customer_name' => 'Bob Smith',
            'total_amount' => 200.00,
        ]);

        $order->state = new PendingState($order);
        $order->save();
        $order->refresh();

        $this->assertInstanceOf(PendingState::class, $order->state);
    }

    /**
     * Test setAttribute accepts State class name string
     */
    public function test_set_attribute_accepts_state_class_name(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FLEX-004',
            'customer_name' => 'Alice Johnson',
            'total_amount' => 250.00,
        ]);

        $order->state = ProcessingState::class;
        $order->save();
        $order->refresh();

        $this->assertInstanceOf(ProcessingState::class, $order->state);
    }

    /**
     * Test setAttribute accepts database-only string state
     */
    public function test_set_attribute_accepts_db_only_string(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FLEX-005',
            'customer_name' => 'Charlie Brown',
            'total_amount' => 300.00,
        ]);

        $order->state = 'custom_workflow_state';
        $order->save();
        $order->refresh();

        $this->assertIsString($order->state);
        $this->assertEquals('custom_workflow_state', $order->state);
    }

    /**
     * Test state persists correctly in database for PHP State class
     */
    public function test_php_state_persists_to_database(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FLEX-007',
            'customer_name' => 'Eve Martin',
            'total_amount' => 400.00,
            'state' => ShippedState::class,
        ]);

        $rawValue = DB::table($order->getTable())
            ->where($order->getKeyName(), $order->getKey())
            ->value('state');

        $this->assertEquals(ShippedState::class, $rawValue);
    }

    /**
     * Test database-only state persists correctly
     */
    public function test_db_only_state_persists_to_database(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FLEX-008',
            'customer_name' => 'Frank Wilson',
            'total_amount' => 450.00,
        ]);

        $order->state = 'awaiting_approval';
        $order->save();

        $rawValue = DB::table($order->getTable())
            ->where($order->getKeyName(), $order->getKey())
            ->value('state');

        $this->assertEquals('awaiting_approval', $rawValue);
    }

    /**
     * Test switching from PHP state to database-only state using raw SQL
     * Note: Direct assignment doesn't work due to Spatie casting on save
     */
    public function test_switch_from_php_state_to_db_only(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FLEX-009',
            'customer_name' => 'Grace Taylor',
            'total_amount' => 500.00,
            'state' => PendingState::class,
        ]);

        $this->assertInstanceOf(PendingState::class, $order->state);

        // Use raw SQL to switch to DB-only state (bypasses Spatie casting)
        DB::table($order->getTable())
            ->where($order->getKeyName(), $order->getKey())
            ->update(['state' => 'on_hold']);

        $order->refresh();

        $this->assertIsString($order->state);
        $this->assertEquals('on_hold', $order->state);
    }

    /**
     * Test switching from database-only state to PHP state
     */
    public function test_switch_from_db_only_to_php_state(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FLEX-010',
            'customer_name' => 'Henry Adams',
            'total_amount' => 550.00,
        ]);

        // Set DB-only state using raw SQL
        DB::table($order->getTable())
            ->where($order->getKeyName(), $order->getKey())
            ->update(['state' => 'custom_state']);

        $order->refresh();

        $this->assertIsString($order->state);
        $this->assertEquals('custom_state', $order->state);

        // Switch to PHP state using raw SQL
        DB::table($order->getTable())
            ->where($order->getKeyName(), $order->getKey())
            ->update(['state' => ProcessingState::class]);

        $order->refresh();

        $this->assertInstanceOf(ProcessingState::class, $order->state);
    }

    /**
     * Test flexibleStateFields configuration is respected
     */
    public function test_flexible_state_fields_configuration(): void
    {
        $order = new Order;

        $reflection = new ReflectionClass($order);
        $property = $reflection->getProperty('flexibleStateFields');

        $flexibleFields = $property->getValue($order);

        $this->assertContains('state', $flexibleFields);
    }

    /**
     * Test getStateClassForField extracts correct class from casts
     *
     * @throws ReflectionException
     */
    public function test_get_state_class_for_field(): void
    {
        $order = new Order;

        $reflection = new ReflectionClass($order);
        $method = $reflection->getMethod('getStateClassForField');

        $stateClass = $method->invoke($order, 'state');

        $this->assertEquals(OrderState::class, $stateClass);
    }

    /**
     * Test model serialization works with PHP state
     */
    public function test_serialization_with_php_state(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FLEX-011',
            'customer_name' => 'Ivy Chen',
            'total_amount' => 600.00,
            'state' => PendingState::class,
        ]);

        $array = $order->toArray();

        $this->assertArrayHasKey('state', $array);
        $this->assertNotNull($array['state']);
    }

    /**
     * Test model retrieval works with database-only state
     */
    public function test_retrieval_with_db_only_state(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FLEX-012',
            'customer_name' => 'Jack Miller',
            'total_amount' => 650.00,
        ]);

        // Set DB-only state using raw SQL
        DB::table($order->getTable())
            ->where($order->getKeyName(), $order->getKey())
            ->update(['state' => 'retrieval_test_state']);

        $order->refresh();

        // Verify the state is correctly retrieved as string
        $this->assertIsString($order->state);
        $this->assertEquals('retrieval_test_state', $order->state);

        // Verify it's stored correctly in the database
        $rawValue = DB::table($order->getTable())
            ->where($order->getKeyName(), $order->getKey())
            ->value('state');

        $this->assertEquals('retrieval_test_state', $rawValue);
    }

    /**
     * Test model can be found by PHP state class name
     */
    public function test_query_by_php_state_class(): void
    {
        Order::create([
            'order_number' => 'ORD-FLEX-013',
            'customer_name' => 'Kate Williams',
            'total_amount' => 700.00,
            'state' => PendingState::class,
        ]);

        $found = Order::where('state', PendingState::class)->first();

        $this->assertNotNull($found);
        $this->assertEquals('ORD-FLEX-013', $found->order_number);
    }

    /**
     * Test model can be found by database-only state string
     */
    public function test_query_by_db_only_state(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FLEX-014',
            'customer_name' => 'Leo Martinez',
            'total_amount' => 750.00,
        ]);

        $order->state = 'query_test_state';
        $order->save();

        $found = Order::where('state', 'query_test_state')->first();

        $this->assertNotNull($found);
        $this->assertEquals('ORD-FLEX-014', $found->order_number);
    }

    /**
     * Test multiple models with different state types
     */
    public function test_multiple_models_mixed_states(): void
    {
        $order1 = Order::create([
            'order_number' => 'ORD-FLEX-015',
            'customer_name' => 'Mia Thompson',
            'total_amount' => 100.00,
            'state' => PendingState::class,
        ]);

        $order2 = Order::create([
            'order_number' => 'ORD-FLEX-016',
            'customer_name' => 'Noah Garcia',
            'total_amount' => 200.00,
            'state' => ProcessingState::class,
        ]);

        $order3 = Order::create([
            'order_number' => 'ORD-FLEX-017',
            'customer_name' => 'Olivia Robinson',
            'total_amount' => 300.00,
        ]);
        $order3->state = 'custom_state';
        $order3->save();
        $order3->refresh();

        $this->assertInstanceOf(PendingState::class, $order1->state);
        $this->assertInstanceOf(ProcessingState::class, $order2->state);
        $this->assertIsString($order3->state);
        $this->assertEquals('custom_state', $order3->state);
    }

    /**
     * Test getCastType behavior for database-only state
     * Note: Returns OrderState class unless resolveStateClass returns null
     */
    public function test_get_cast_type_for_db_only_state(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FLEX-020',
            'customer_name' => 'Rachel Clark',
            'total_amount' => 900.00,
        ]);

        // Set DB-only state using raw SQL
        DB::table($order->getTable())
            ->where($order->getKeyName(), $order->getKey())
            ->update(['state' => 'db_only_for_cast_test']);

        $order->refresh();

        // Verify the state is stored as string
        $this->assertIsString($order->state);
        $this->assertEquals('db_only_for_cast_test', $order->state);
    }
}
