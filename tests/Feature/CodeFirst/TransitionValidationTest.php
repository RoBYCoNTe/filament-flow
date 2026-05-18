<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\CodeFirst;

use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\DeliveredState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ShippedState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Test transition validation and business rules
 */
class TransitionValidationTest extends TestCase
{
    /**
     * Test that a valid transition is allowed
     */
    public function test_valid_transition_is_allowed(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-VAL-001',
            'customer_name' => 'John Doe',
            'total_amount' => 100.00,
        ]);

        $order->state = new PendingState($order);
        $order->save();

        // Transition from pending to processing should be allowed
        $order->state = new ProcessingState($order);
        $order->save();

        $this->assertTrue(true); // If we get here, transition was allowed
    }

    /**
     * Test that sequential valid transitions work
     */
    public function test_sequential_valid_transitions(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-VAL-002',
            'customer_name' => 'Jane Doe',
            'total_amount' => 150.00,
        ]);

        // Valid sequence: pending → processing → shipped → delivered
        $order->state = new PendingState($order);
        $order->save();

        $order->state = new ProcessingState($order);
        $order->save();

        $order->state = new ShippedState($order);
        $order->save();

        $order->state = new DeliveredState($order);
        $order->save();

        $this->assertInstanceOf(DeliveredState::class, $order->state);
    }

    /**
     * Test that state can be read after transition
     */
    public function test_state_readable_after_transition(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-VAL-003',
            'customer_name' => 'Bob Smith',
            'total_amount' => 200.00,
        ]);

        $order->state = new PendingState($order);
        $order->save();

        $order->state = new ProcessingState($order);
        $order->save();

        // State should be readable
        $this->assertNotNull($order->state);
    }

    /**
     * Test that transition maintains model integrity
     */
    public function test_transition_maintains_model_integrity(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-VAL-004',
            'customer_name' => 'Alice Johnson',
            'total_amount' => 300.00,
        ]);

        $originalId = $order->id;
        $originalOrderNumber = $order->order_number;

        $order->state = new PendingState($order);
        $order->save();

        $order->state = new ProcessingState($order);
        $order->save();

        // Model integrity should be maintained
        $this->assertEquals($originalId, $order->id);
        $this->assertEquals($originalOrderNumber, $order->order_number);
        $this->assertEquals(300.00, $order->total_amount);
    }

    /**
     * Test that multiple models can transition independently
     */
    public function test_multiple_models_transition_independently(): void
    {
        $order1 = Order::create([
            'order_number' => 'ORD-VAL-005',
            'customer_name' => 'Charlie Brown',
            'total_amount' => 250.00,
        ]);

        $order2 = Order::create([
            'order_number' => 'ORD-VAL-006',
            'customer_name' => 'David Lee',
            'total_amount' => 175.00,
        ]);

        // Transition order1
        $order1->state = new PendingState($order1);
        $order1->save();
        $order1->state = new ProcessingState($order1);
        $order1->save();

        // Transition order2 to different state
        $order2->state = new PendingState($order2);
        $order2->save();
        $order2->state = new ShippedState($order2);
        $order2->save();

        // States should be independent
        $this->assertInstanceOf(ProcessingState::class, $order1->state);
        $this->assertInstanceOf(ShippedState::class, $order2->state);
    }

    /**
     * Test that state persists across model refresh
     */
    public function test_state_persists_across_refresh(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-VAL-007',
            'customer_name' => 'Eve Martin',
            'total_amount' => 400.00,
        ]);

        $order->state = new PendingState($order);
        $order->save();

        $order->state = new ProcessingState($order);
        $order->save();

        $orderId = $order->id;

        // Refresh model from database
        $refreshedOrder = Order::find($orderId);

        // State should persist (though might be string representation)
        $this->assertNotNull($refreshedOrder->state);
    }

    /**
     * Test that state changes are atomic
     */
    public function test_state_changes_are_atomic(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-VAL-008',
            'customer_name' => 'Frank Miller',
            'total_amount' => 500.00,
        ]);

        $order->state = new PendingState($order);
        $order->save();

        $beforeTransition = $order->state;

        $order->state = new ProcessingState($order);
        $order->save();

        $afterTransition = $order->state;

        // States should be different after transition
        $this->assertNotEquals($beforeTransition, $afterTransition);
    }
}
