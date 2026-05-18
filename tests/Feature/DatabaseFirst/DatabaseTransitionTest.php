<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Models\WorkflowStateTransition;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

/**
 * Test database-only state transitions (without PHP State classes)
 */
class DatabaseTransitionTest extends TestCase
{
    /**
     * Test transition between database-only states
     */
    public function test_transition_between_database_only_states(): void
    {
        $workflow = $this->createTestWorkflow();

        $pendingState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'label' => 'Pending',
        ]);

        $processingState = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'label' => 'Processing',
        ]);

        $this->createWorkflowTransition($workflow, $pendingState, $processingState, [
            'name' => 'start_processing',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-DB-001',
            'customer_name' => 'John Doe',
            'total_amount' => 100.00,
        ]);

        // Set initial state as string (database-only)
        $order->state = 'pending';
        $order->save();

        $this->assertEquals('pending', $order->state);

        // Transition to processing
        $order->transitionTo('processing');

        $this->assertEquals('processing', $order->state);
    }

    /**
     * Test database state transition persists
     */
    public function test_database_state_transition_persists(): void
    {
        $workflow = $this->createTestWorkflow();

        $state1 = $this->createWorkflowState($workflow, ['name' => 'state1']);
        $state2 = $this->createWorkflowState($workflow, ['name' => 'state2']);
        $this->createWorkflowTransition($workflow, $state1, $state2);

        $order = Order::create([
            'order_number' => 'ORD-DB-002',
            'customer_name' => 'Jane Doe',
            'total_amount' => 150.00,
        ]);

        $order->state = 'state1';
        $order->save();

        $orderId = $order->id;

        $order->transitionTo('state2');

        // Refresh from database
        $refreshedOrder = Order::find($orderId);

        $this->assertEquals('state2', $refreshedOrder->state);
    }

    /**
     * Test database state transition is logged
     */
    public function test_database_state_transition_logged(): void
    {
        $workflow = $this->createTestWorkflow();

        $state1 = $this->createWorkflowState($workflow, [
            'name' => 'state1',
            'label' => 'State 1',
        ]);
        $state2 = $this->createWorkflowState($workflow, [
            'name' => 'state2',
            'label' => 'State 2',
        ]);
        $this->createWorkflowTransition($workflow, $state1, $state2);

        $order = Order::create([
            'order_number' => 'ORD-DB-003',
            'customer_name' => 'Bob Smith',
            'total_amount' => 200.00,
        ]);

        $order->state = 'state1';
        $order->save();

        $order->transitionTo('state2');

        // Check if transition was logged
        $this->assertDatabaseHas('workflow_state_transitions', [
            'transitionable_type' => Order::class,
            'transitionable_id' => $order->id,
            'from_state' => 'state1',
            'to_state' => 'state2',
        ]);
    }

    /**
     * Test multiple database transitions in sequence
     */
    public function test_multiple_database_transitions(): void
    {
        $workflow = $this->createTestWorkflow();

        $state1 = $this->createWorkflowState($workflow, ['name' => 'state1']);
        $state2 = $this->createWorkflowState($workflow, ['name' => 'state2']);
        $state3 = $this->createWorkflowState($workflow, ['name' => 'state3']);

        $this->createWorkflowTransition($workflow, $state1, $state2);
        $this->createWorkflowTransition($workflow, $state2, $state3);

        $order = Order::create([
            'order_number' => 'ORD-DB-004',
            'customer_name' => 'Alice Johnson',
            'total_amount' => 300.00,
        ]);

        $order->state = 'state1';
        $order->save();

        $order->transitionTo('state2');
        $this->assertEquals('state2', $order->state);

        $order->transitionTo('state3');
        $this->assertEquals('state3', $order->state);
    }

    /**
     * Test transition with metadata
     */
    public function test_transition_with_metadata(): void
    {
        $workflow = $this->createTestWorkflow();

        $state1 = $this->createWorkflowState($workflow, ['name' => 'state1']);
        $state2 = $this->createWorkflowState($workflow, ['name' => 'state2']);
        $this->createWorkflowTransition($workflow, $state1, $state2);

        $order = Order::create([
            'order_number' => 'ORD-DB-005',
            'customer_name' => 'Charlie Brown',
            'total_amount' => 250.00,
        ]);

        $order->state = 'state1';
        $order->save();

        $order->transitionTo('state2', ['note' => 'Approved by manager']);

        $this->assertEquals('state2', $order->state);
    }

    /**
     * Test invalid database transition is rejected
     */
    public function test_invalid_database_transition_rejected(): void
    {
        $workflow = $this->createTestWorkflow();

        $state1 = $this->createWorkflowState($workflow, ['name' => 'state1']);
        $state2 = $this->createWorkflowState($workflow, ['name' => 'state2']);
        $this->createWorkflowState($workflow, ['name' => 'state3']);

        // Only create transition from state1 to state2
        $this->createWorkflowTransition($workflow, $state1, $state2);

        $order = Order::create([
            'order_number' => 'ORD-DB-006',
            'customer_name' => 'David Lee',
            'total_amount' => 175.00,
        ]);

        $order->state = 'state1';
        $order->save();

        // Try to transition directly to state3 (no transition defined)
        $this->expectException(TransitionNotFound::class);

        $order->transitionTo('state3');
    }

    /**
     * Test transition history includes workflow info
     */
    public function test_transition_history_includes_workflow_info(): void
    {
        $workflow = $this->createTestWorkflow(['name' => 'Test Workflow']);

        $state1 = $this->createWorkflowState($workflow, [
            'name' => 'state1',
            'label' => 'State 1',
        ]);
        $state2 = $this->createWorkflowState($workflow, [
            'name' => 'state2',
            'label' => 'State 2',
        ]);
        $transition = $this->createWorkflowTransition($workflow, $state1, $state2);

        $order = Order::create([
            'order_number' => 'ORD-DB-007',
            'customer_name' => 'Eve Martin',
            'total_amount' => 400.00,
        ]);

        $order->state = 'state1';
        $order->save();

        $order->transitionTo('state2');

        $history = WorkflowStateTransition::where('transitionable_id', $order->id)
            ->where('transitionable_type', Order::class)
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals($workflow->id, $history->workflow_id);
        $this->assertEquals($transition->id, $history->transition_id);
        $this->assertEquals('State 1', $history->from_state_label);
        $this->assertEquals('State 2', $history->to_state_label);
    }
}
