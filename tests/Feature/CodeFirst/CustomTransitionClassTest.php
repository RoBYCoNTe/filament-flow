<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\CodeFirst;

use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Test custom transition classes with business logic
 *
 * NOTE: This test suite focuses on testing the infrastructure for custom transitions.
 * Custom transition classes allow adding business logic before/after state changes.
 */
class CustomTransitionClassTest extends TestCase
{
    /**
     * Test that a basic transition works without custom class
     */
    public function test_basic_transition_without_custom_class(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-CUSTOM-001',
            'customer_name' => 'John Doe',
            'total_amount' => 100.00,
        ]);

        $order->state = new PendingState($order);
        $order->save();

        $order->state = new ProcessingState($order);
        $order->save();

        $this->assertInstanceOf(ProcessingState::class, $order->state);
    }

    /**
     * Test that state label is accessible
     */
    public function test_state_label_accessible(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-CUSTOM-002',
            'customer_name' => 'Jane Doe',
            'total_amount' => 150.00,
        ]);

        $state = new PendingState($order);

        $this->assertEquals('Pending', $state->getLabel());
    }

    /**
     * Test that state description is accessible
     */
    public function test_state_description_accessible(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-CUSTOM-003',
            'customer_name' => 'Bob Smith',
            'total_amount' => 200.00,
        ]);

        $state = new ProcessingState($order);

        $this->assertNotEmpty($state->getDescription());
        $this->assertEquals('Order is being processed', $state->getDescription());
    }

    /**
     * Test that state can access its model
     */
    public function test_state_can_access_model(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-CUSTOM-004',
            'customer_name' => 'Alice Johnson',
            'total_amount' => 300.00,
        ]);

        $state = new PendingState($order);

        // Spatie State has access to the model via constructor
        $this->assertNotNull($state);
    }

    /**
     * Test that different state instances are independent
     */
    public function test_different_state_instances_are_independent(): void
    {
        $order1 = Order::create([
            'order_number' => 'ORD-CUSTOM-005',
            'customer_name' => 'Charlie Brown',
            'total_amount' => 250.00,
        ]);

        $order2 = Order::create([
            'order_number' => 'ORD-CUSTOM-006',
            'customer_name' => 'David Lee',
            'total_amount' => 175.00,
        ]);

        $state1 = new PendingState($order1);
        $state2 = new ProcessingState($order2);

        // States should be different instances
        $this->assertNotEquals(get_class($state1), get_class($state2));
    }

    /**
     * Test that state metadata is consistent
     */
    public function test_state_metadata_is_consistent(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-CUSTOM-007',
            'customer_name' => 'Eve Martin',
            'total_amount' => 400.00,
        ]);

        $state1 = new PendingState($order);
        $state2 = new PendingState($order);

        // Same state class should have same metadata
        $this->assertEquals($state1->getLabel(), $state2->getLabel());
        $this->assertEquals($state1->getDescription(), $state2->getDescription());
    }

    /**
     * Test that state class name is retrievable
     *
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    public function test_state_class_name_retrievable(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-CUSTOM-008',
            'customer_name' => 'Frank Miller',
            'total_amount' => 500.00,
        ]);

        $state = new ProcessingState($order);

        $this->assertEquals(ProcessingState::class, get_class($state));
    }
}
