<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Hybrid;

use DB;
use ReflectionClass;
use ReflectionException;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\DeliveredState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\OrderState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ShippedState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Test state resolution logic - how the system resolves between PHP State classes and database strings
 */
class StateResolutionTest extends TestCase
{
    /**
     * Test resolving a PHP state class returns the correct instance
     */
    public function test_resolve_php_state_class(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-RES-001',
            'customer_name' => 'John Doe',
            'total_amount' => 100.00,
            'state' => PendingState::class,
        ]);

        // State should resolve to PendingState instance
        $this->assertInstanceOf(PendingState::class, $order->state);
        $this->assertInstanceOf(OrderState::class, $order->state);
    }

    /**
     * Test resolving a database string state returns the string
     */
    public function test_resolve_database_state_string(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-RES-002',
            'customer_name' => 'Jane Doe',
            'total_amount' => 150.00,
        ]);

        // Set database-only state using raw SQL
        DB::table($order->getTable())
            ->where($order->getKeyName(), $order->getKey())
            ->update(['state' => 'custom_db_state']);

        $order->refresh();

        // State should resolve to string
        $this->assertIsString($order->state);
        $this->assertEquals('custom_db_state', $order->state);
    }

    /**
     * Test state class resolution from full class name
     */
    public function test_resolve_state_from_full_class_name(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-RES-003',
            'customer_name' => 'Bob Smith',
            'total_amount' => 200.00,
        ]);

        // Set state using full class name
        DB::table($order->getTable())
            ->where($order->getKeyName(), $order->getKey())
            ->update(['state' => ProcessingState::class]);

        $order->refresh();

        // Should resolve to ProcessingState instance
        $this->assertInstanceOf(ProcessingState::class, $order->state);
    }

    /**
     * Test all known PHP states resolve correctly
     */
    public function test_all_known_states_resolve(): void
    {
        $states = [
            PendingState::class,
            ProcessingState::class,
            ShippedState::class,
            DeliveredState::class,
        ];

        foreach ($states as $index => $stateClass) {
            $order = Order::create([
                'order_number' => 'ORD-RES-004-'.$index,
                'customer_name' => 'Test User '.$index,
                'total_amount' => 100.00 + $index,
                'state' => $stateClass,
            ]);

            $this->assertInstanceOf($stateClass, $order->state);
        }
    }

    /**
     * Test unknown state string does not get cast to PHP class
     */
    public function test_unknown_state_remains_string(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-RES-005',
            'customer_name' => 'Alice Johnson',
            'total_amount' => 250.00,
        ]);

        // Set a state that is not a known PHP class
        DB::table($order->getTable())
            ->where($order->getKeyName(), $order->getKey())
            ->update(['state' => 'unknown_workflow_state']);

        $order->refresh();

        // Should remain as string
        $this->assertIsString($order->state);
        $this->assertEquals('unknown_workflow_state', $order->state);
    }

    /**
     * Test state resolution after model refresh
     */
    public function test_state_resolution_after_refresh(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-RES-006',
            'customer_name' => 'Charlie Brown',
            'total_amount' => 300.00,
            'state' => PendingState::class,
        ]);

        $this->assertInstanceOf(PendingState::class, $order->state);

        // Refresh and check again
        $order->refresh();

        $this->assertInstanceOf(PendingState::class, $order->state);
    }

    /**
     * Test state resolution after model reload from database
     */
    public function test_state_resolution_after_reload(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-RES-007',
            'customer_name' => 'David Lee',
            'total_amount' => 350.00,
            'state' => ShippedState::class,
        ]);

        // Reload from database
        $reloadedOrder = Order::find($order->id);

        $this->assertInstanceOf(ShippedState::class, $reloadedOrder->state);
    }

    /**
     * Test state resolution with null value
     */
    public function test_state_resolution_with_null(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-RES-008',
            'customer_name' => 'Eve Martin',
            'total_amount' => 400.00,
        ]);

        // Set state to null
        DB::table($order->getTable())
            ->where($order->getKeyName(), $order->getKey())
            ->update(['state' => null]);

        $order->refresh();

        // Null state handling - depends on model configuration
        $this->assertNull($order->state);
    }

    /**
     * Test state resolution respects model casts
     */
    public function test_state_resolution_respects_casts(): void
    {
        $order = new Order;
        $casts = $order->getCasts();

        // Verify state is cast to OrderState
        $this->assertArrayHasKey('state', $casts);
        $this->assertEquals(OrderState::class, $casts['state']);
    }

    /**
     * Test state can be compared after resolution
     */
    public function test_state_comparison_after_resolution(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-RES-009',
            'customer_name' => 'Frank Wilson',
            'total_amount' => 450.00,
            'state' => PendingState::class,
        ]);

        // Compare using instanceof
        $this->assertTrue($order->state instanceof PendingState);
        $this->assertFalse($order->state instanceof ProcessingState);
    }

    /**
     * Test state resolution consistency across multiple reads
     */
    public function test_state_resolution_consistency(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-RES-010',
            'customer_name' => 'Grace Taylor',
            'total_amount' => 500.00,
            'state' => ProcessingState::class,
        ]);

        // Read state multiple times
        $state1 = $order->state;
        $state2 = $order->state;
        $state3 = $order->state;

        // All should be ProcessingState instances
        $this->assertInstanceOf(ProcessingState::class, $state1);
        $this->assertInstanceOf(ProcessingState::class, $state2);
        $this->assertInstanceOf(ProcessingState::class, $state3);
    }

    /**
     * Test state resolution with mixed states in collection
     */
    public function test_state_resolution_in_collection(): void
    {
        // Create orders with different state types
        Order::create([
            'order_number' => 'ORD-RES-011-A',
            'customer_name' => 'User A',
            'total_amount' => 100.00,
            'state' => PendingState::class,
        ]);

        Order::create([
            'order_number' => 'ORD-RES-011-B',
            'customer_name' => 'User B',
            'total_amount' => 200.00,
            'state' => ProcessingState::class,
        ]);

        $orderC = Order::create([
            'order_number' => 'ORD-RES-011-C',
            'customer_name' => 'User C',
            'total_amount' => 300.00,
        ]);

        // Set database-only state
        DB::table($orderC->getTable())
            ->where($orderC->getKeyName(), $orderC->getKey())
            ->update(['state' => 'custom_state']);

        // Query all orders
        $orders = Order::whereIn('order_number', [
            'ORD-RES-011-A',
            'ORD-RES-011-B',
            'ORD-RES-011-C',
        ])->orderBy('order_number')->get();

        // Check each order has correct state type
        $this->assertInstanceOf(PendingState::class, $orders[0]->state);
        $this->assertInstanceOf(ProcessingState::class, $orders[1]->state);
        $this->assertIsString($orders[2]->state);
        $this->assertEquals('custom_state', $orders[2]->state);
    }

    /**
     * Test getStateClassForField returns correct class
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
     * Test state label accessible after resolution
     */
    public function test_state_label_after_resolution(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-RES-012',
            'customer_name' => 'Henry Adams',
            'total_amount' => 550.00,
            'state' => PendingState::class,
        ]);

        // PHP state should have label method
        $this->assertEquals('Pending', $order->state->getLabel());
    }

    /**
     * Test state description accessible after resolution
     */
    public function test_state_description_after_resolution(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-RES-013',
            'customer_name' => 'Ivy Chen',
            'total_amount' => 600.00,
            'state' => ProcessingState::class,
        ]);

        // PHP state should have description method
        $this->assertEquals('Order is being processed', $order->state->getDescription());
    }
}
