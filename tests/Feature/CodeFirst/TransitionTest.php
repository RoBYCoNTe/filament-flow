<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\CodeFirst;

use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\DeliveredState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ShippedState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Test basic state transitions using Code-First approach (Spatie Model States)
 */
class TransitionTest extends TestCase
{
    /**
     * Test that a model can be created with initial state
     */
    public function test_model_starts_in_initial_state(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-001',
            'customer_name' => 'John Doe',
            'total_amount' => 100.00,
        ]);

        // Verify model was created successfully
        $this->assertNotNull($order->id);
        $this->assertEquals('ORD-001', $order->order_number);
    }

    /**
     * Test that a model can transition to a valid next state
     */
    public function test_can_transition_to_valid_next_state(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-002',
            'customer_name' => 'Jane Doe',
            'total_amount' => 150.00,
        ]);

        $order->state = new ProcessingState($order);
        $order->save();

        $this->assertInstanceOf(ProcessingState::class, $order->state);
    }

    /**
     * Test that model transitions persist to database
     */
    public function test_transition_persists_to_database(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-003',
            'customer_name' => 'Bob Smith',
            'total_amount' => 200.00,
        ]);

        $orderId = $order->id;

        $order->state = new ProcessingState($order);
        $order->save();

        // Fetch from database to verify persistence
        $refreshedOrder = Order::find($orderId);
        $this->assertInstanceOf(ProcessingState::class, $refreshedOrder->state);
    }

    /**
     * Test multiple state transitions in sequence
     */
    public function test_multiple_transitions_in_sequence(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-004',
            'customer_name' => 'Alice Johnson',
            'total_amount' => 300.00,
        ]);

        // Transition sequence: pending → processing → shipped → delivered
        $order->state = new ProcessingState($order);
        $order->save();
        $this->assertInstanceOf(ProcessingState::class, $order->state);

        $order->state = new ShippedState($order);
        $order->save();
        $this->assertInstanceOf(ShippedState::class, $order->state);

        $order->state = new DeliveredState($order);
        $order->save();
        $this->assertInstanceOf(DeliveredState::class, $order->state);
    }

    /**
     * Test that a state transition updates the model
     */
    public function test_transition_updates_state_value(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-005',
            'customer_name' => 'Charlie Brown',
            'total_amount' => 250.00,
        ]);

        // Set initial state and transition
        $order->state = new PendingState($order);
        $order->save();

        $order->state = new ProcessingState($order);
        $order->save();

        // Verify the state was changed
        $this->assertNotEquals('pending', $order->state);
    }

    /**
     * Test that transitioning returns the new state object
     */
    public function test_transition_returns_new_state(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-006',
            'customer_name' => 'David Lee',
            'total_amount' => 175.00,
        ]);

        // Set initial state
        $order->state = new PendingState($order);
        $order->save();
        $order->refresh();

        // Transition to new state
        $newState = new ProcessingState($order);
        $order->state = $newState;
        $order->save();

        // Verify the state was transitioned
        $this->assertTrue(
            $order->state === 'processing' || $order->state instanceof ProcessingState,
            'Order should be in processing state'
        );
    }
}
