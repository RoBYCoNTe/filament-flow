<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\CodeFirst;

use Illuminate\Support\Carbon;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateTransition;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ShippedState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Test transition history logging and tracking
 *
 * Tests that transitions are logged even with Code-First approach (Spatie).
 * With Code-First: workflow_id and transition_id will be null, but transition info is still logged.
 */
class TransitionHistoryTest extends TestCase
{
    /**
     * Test that a transition creates a history record
     */
    public function test_transition_creates_history_record(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-HIST-001',
            'customer_name' => 'John Doe',
            'total_amount' => 100.00,
        ]);

        $order->state = new PendingState($order);
        $order->save();

        // Use transition() method to trigger logging
        $order->transitionTo(ProcessingState::class);

        // Check if transition was logged
        $this->assertDatabaseHas('workflow_state_transitions', [
            'transitionable_type' => Order::class,
            'transitionable_id' => $order->id,
        ]);
    }

    /**
     * Test that history records the from and to states
     */
    public function test_history_records_from_and_to_states(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-HIST-002',
            'customer_name' => 'Jane Doe',
            'total_amount' => 150.00,
        ]);

        $order->state = new PendingState($order);
        $order->save();

        $order->transitionTo(ProcessingState::class);

        $history = WorkflowStateTransition::where('transitionable_id', $order->id)
            ->where('transitionable_type', Order::class)
            ->latest()
            ->first();

        $this->assertNotNull($history);
        $this->assertStringContainsString('Pending', $history->from_state);
        $this->assertStringContainsString('Processing', $history->to_state);
    }

    /**
     * Test that history records timestamp
     */
    public function test_history_records_timestamp(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-HIST-003',
            'customer_name' => 'Bob Smith',
            'total_amount' => 200.00,
        ]);

        $order->state = new PendingState($order);
        $order->save();

        $order->transitionTo(ProcessingState::class);

        $history = WorkflowStateTransition::where('transitionable_id', $order->id)
            ->where('transitionable_type', Order::class)
            ->latest()
            ->first();

        $this->assertNotNull($history);
        $this->assertNotNull($history->created_at);
        $this->assertInstanceOf(Carbon::class, $history->created_at);
    }

    /**
     * Test that multiple transitions are logged in sequence
     */
    public function test_multiple_transitions_are_logged(): void
    {
        $user = $this->createTestUser(['name' => 'Shipper', 'email' => 'hist-shipper@test.com']);

        $order = Order::create([
            'order_number' => 'ORD-HIST-004',
            'customer_name' => 'Alice Johnson',
            'total_amount' => 300.00,
        ]);

        // Transition sequence: pending → processing → shipped
        $order->state = new PendingState($order);
        $order->save();

        $order->transitionTo(ProcessingState::class);

        // ToShippedTransition requires user assignment
        $order->transitionTo(ShippedState::class, [
            'assigned_users' => [$user->id],
            'tracking_number' => 'TRK-HIST-001',
            'carrier' => 'ups',
        ]);

        $historyCount = WorkflowStateTransition::where('transitionable_id', $order->id)
            ->where('transitionable_type', Order::class)
            ->count();

        // We expect at least 2 transitions (pending→processing, processing→shipped)
        $this->assertGreaterThanOrEqual(2, $historyCount);
    }

    /**
     * Test that history is queryable by record
     */
    public function test_history_queryable_by_record(): void
    {
        $user = $this->createTestUser(['name' => 'Shipper', 'email' => 'hist-shipper2@test.com']);

        $order1 = Order::create([
            'order_number' => 'ORD-HIST-005',
            'customer_name' => 'Charlie Brown',
            'total_amount' => 250.00,
        ]);

        $order2 = Order::create([
            'order_number' => 'ORD-HIST-006',
            'customer_name' => 'David Lee',
            'total_amount' => 175.00,
        ]);

        $order1->state = new PendingState($order1);
        $order1->save();
        $order1->transitionTo(ProcessingState::class);

        $order2->state = new PendingState($order2);
        $order2->save();
        $order2->transitionTo(ProcessingState::class);

        // ToShippedTransition requires user assignment
        $order2->transitionTo(ShippedState::class, [
            'assigned_users' => [$user->id],
            'tracking_number' => 'TRK-HIST-002',
            'carrier' => 'fedex',
        ]);

        $order1History = WorkflowStateTransition::where('transitionable_id', $order1->id)
            ->where('transitionable_type', Order::class)
            ->get();

        $order2History = WorkflowStateTransition::where('transitionable_id', $order2->id)
            ->where('transitionable_type', Order::class)
            ->get();

        $this->assertGreaterThan(0, $order1History->count());
        $this->assertGreaterThan(0, $order2History->count());

        // Verify that histories are separate
        $this->assertNotEquals($order1History->pluck('id'), $order2History->pluck('id'));
    }

    /**
     * Test that history is queryable by date range
     */
    public function test_history_queryable_by_date_range(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-HIST-007',
            'customer_name' => 'Eve Martin',
            'total_amount' => 400.00,
        ]);

        $order->state = new PendingState($order);
        $order->save();

        $order->transitionTo(ProcessingState::class);

        $startDate = now()->subMinute();
        $endDate = now()->addMinute();

        $history = WorkflowStateTransition::where('transitionable_id', $order->id)
            ->where('transitionable_type', Order::class)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $this->assertGreaterThan(0, $history->count());
    }
}
