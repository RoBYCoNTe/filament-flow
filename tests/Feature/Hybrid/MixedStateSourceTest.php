<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Hybrid;

use DB;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Test mixing Code-First (Spatie) and Database-First states in same workflow
 */
class MixedStateSourceTest extends TestCase
{
    /**
     * Test model can transition from PHP state to database string state
     */
    public function test_transition_from_php_state_to_db_string(): void
    {
        $workflow = $this->createTestWorkflow();

        // Create database states for the workflow - MUST include pending state
        $pendingDbState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'label' => 'Pending',
            'class_name' => PendingState::class,
        ]);

        $customState = $this->createWorkflowState($workflow, [
            'name' => 'custom_status',
            'label' => 'Custom Status',
        ]);

        // Create transition from pending to custom_status
        $this->createWorkflowTransition($workflow, $pendingDbState, $customState);

        $order = Order::create([
            'order_number' => 'ORD-HYBRID-001',
            'customer_name' => 'John Doe',
            'total_amount' => 100.00,
            'state' => PendingState::class,
        ]);

        // Order starts in PendingState (PHP/Spatie)
        $this->assertInstanceOf(PendingState::class, $order->state);

        // Transition to database string state
        $order->transitionTo('custom_status');

        // After transition, state is a string (database-only)
        $this->assertEquals('custom_status', $order->state);
    }

    /**
     * Test model can transition from database string to PHP state
     */
    public function test_transition_from_db_string_to_php_state(): void
    {
        $workflow = $this->createTestWorkflow();

        $customState = $this->createWorkflowState($workflow, [
            'name' => 'custom_status',
            'label' => 'Custom Status',
        ]);

        // Create transition from custom_status to ProcessingState
        $processingDbState = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'label' => 'Processing (DB)',
            'class_name' => ProcessingState::class,
        ]);

        $this->createWorkflowTransition($workflow, $customState, $processingDbState);

        $order = Order::create([
            'order_number' => 'ORD-HYBRID-002',
            'customer_name' => 'Jane Doe',
            'total_amount' => 150.00,
        ]);

        // Set order to database string state
        $order->state = 'custom_status';
        $order->save();
        $order->refresh();

        $this->assertEquals('custom_status', $order->state);

        // Transition to PHP state
        $order->transitionTo(ProcessingState::class);

        // After transition, state is a ProcessingState instance
        $this->assertInstanceOf(ProcessingState::class, $order->state);
    }

    /**
     * Test mixed state transitions are logged correctly
     */
    public function test_mixed_transitions_logged(): void
    {
        $workflow = $this->createTestWorkflow();

        $pendingDbState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'label' => 'Pending',
            'class_name' => PendingState::class,
        ]);

        $customState = $this->createWorkflowState($workflow, [
            'name' => 'custom',
            'label' => 'Custom',
        ]);

        $this->createWorkflowTransition($workflow, $pendingDbState, $customState);

        $order = Order::create([
            'order_number' => 'ORD-HYBRID-003',
            'customer_name' => 'Bob Smith',
            'total_amount' => 200.00,
            'state' => PendingState::class,
        ]);

        // Transition from PHP state to DB string
        $order->transitionTo('custom');

        // Check that transition was logged
        $this->assertTransitionLogged($order, PendingState::class, 'custom');
    }

    /**
     * Test flexible states handle mixed sources transparently
     */
    public function test_flexible_states_transparent(): void
    {
        $workflow = $this->createTestWorkflow();

        $pendingDbState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        $customState1 = $this->createWorkflowState($workflow, ['name' => 'custom1']);
        $customState2 = $this->createWorkflowState($workflow, ['name' => 'custom2']);

        $this->createWorkflowTransition($workflow, $pendingDbState, $customState1);
        $this->createWorkflowTransition($workflow, $customState1, $customState2);

        $order = Order::create([
            'order_number' => 'ORD-HYBRID-004',
            'customer_name' => 'Charlie Brown',
            'total_amount' => 250.00,
            'state' => PendingState::class,
        ]);

        // Start in PHP state
        $initialState = $order->state;
        $this->assertNotNull($initialState);

        // Transition to DB string state
        $order->transitionTo('custom1');

        // State should now be a string
        $this->assertIsString($order->state);

        // Can still work with the state transparently
        $order->transitionTo('custom2');
        $this->assertEquals('custom2', $order->state);
    }

    /**
     * Test assignments work with mixed states
     */
    public function test_assignments_with_mixed_states(): void
    {
        $user = $this->createTestUser(['name' => 'Test User', 'email' => 'test@example.com']);

        $order = Order::create([
            'order_number' => 'ORD-HYBRID-005',
            'customer_name' => 'David Lee',
            'total_amount' => 175.00,
        ]);

        // Assign user while in PHP state
        $order->assignTo($user, 'primary');
        $this->assertTrue($order->isAssignedTo($user));

        // Change state to DB string
        $workflow = $this->createTestWorkflow();
        $customState = $this->createWorkflowState($workflow, ['name' => 'custom']);

        $order->state = $customState->name;
        $order->save();

        // Assignment should still be valid
        $this->assertTrue($order->isAssignedTo($user));
    }

    /**
     * Test history tracking across mixed state transitions
     */
    public function test_history_across_mixed_states(): void
    {
        $workflow = $this->createTestWorkflow();

        $pendingDbState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'label' => 'Pending',
            'class_name' => PendingState::class,
        ]);

        $dbState = $this->createWorkflowState($workflow, [
            'name' => 'database_state',
            'label' => 'Database State',
        ]);

        $this->createWorkflowTransition($workflow, $pendingDbState, $dbState);

        $order = Order::create([
            'order_number' => 'ORD-HYBRID-006',
            'customer_name' => 'Eve Martin',
            'total_amount' => 400.00,
            'state' => PendingState::class,
        ]);

        // Transition from PHP to DB
        $order->transitionTo('database_state');

        // Check history
        $lastTransition = $this->getLastTransition($order);

        $this->assertNotNull($lastTransition);
        $this->assertEquals(PendingState::class, $lastTransition->from_state);
        $this->assertEquals('database_state', $lastTransition->to_state);
    }

    /**
     * Test state detection works across mixed sources
     */
    public function test_state_detection_mixed(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-HYBRID-007',
            'customer_name' => 'Frank Miller',
            'total_amount' => 550.00,
            'state' => PendingState::class,
        ]);

        // PHP state detected as instance
        $this->assertInstanceOf(PendingState::class, $order->state);

        // Set to string state using raw SQL to bypass all casting
        DB::table($order->getTable())
            ->where($order->getKeyName(), $order->getKey())
            ->update(['state' => 'custom_state']);

        $order->refresh();

        // DB string state detected as string
        $this->assertIsString($order->state);
        $this->assertEquals('custom_state', $order->state);
    }

    /**
     * Test multiple mixed transitions in sequence
     */
    public function test_multiple_mixed_transitions(): void
    {
        $workflow = $this->createTestWorkflow();

        $pendingDbState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        $state1 = $this->createWorkflowState($workflow, ['name' => 'state1']);
        $state2 = $this->createWorkflowState($workflow, [
            'name' => 'state2',
            'class_name' => ProcessingState::class,
        ]);
        $state3 = $this->createWorkflowState($workflow, ['name' => 'state3']);

        $this->createWorkflowTransition($workflow, $pendingDbState, $state1);
        $this->createWorkflowTransition($workflow, $state1, $state2);
        $this->createWorkflowTransition($workflow, $state2, $state3);

        $order = Order::create([
            'order_number' => 'ORD-HYBRID-008',
            'customer_name' => 'Grace Wilson',
            'total_amount' => 300.00,
            'state' => PendingState::class,
        ]);

        // Start in PHP state
        $this->assertInstanceOf(PendingState::class, $order->state);

        // Transition 1: PHP state to DB string
        $order->transitionTo('state1');
        $this->assertEquals('state1', $order->state);

        // Transition 2: DB string to PHP state (via class name in DB)
        $order->transitionTo(ProcessingState::class);
        $this->assertInstanceOf(ProcessingState::class, $order->state);

        // Transition 3: PHP state to DB string
        $order->transitionTo('state3');
        $this->assertEquals('state3', $order->state);
    }
}
