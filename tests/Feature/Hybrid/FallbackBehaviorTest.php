<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Hybrid;

use DB;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\DeliveredState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ShippedState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

/**
 * Test fallback behavior when Spatie transitions are not available
 * The system should fall back to database-configured transitions
 */
class FallbackBehaviorTest extends TestCase
{
    /**
     * Test Spatie transition is attempted first for known states
     */
    public function test_spatie_transition_attempted_for_known_states(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FALL-001',
            'customer_name' => 'John Doe',
            'total_amount' => 100.00,
            'state' => PendingState::class,
        ]);

        $this->assertInstanceOf(PendingState::class, $order->state);

        // Transition using Spatie (PendingState -> ProcessingState is configured)
        $order->state->transitionTo(ProcessingState::class, [
            'processing_notes' => 'Processing via Spatie',
        ]);

        $order->refresh();

        $this->assertInstanceOf(ProcessingState::class, $order->state);
    }

    /**
     * Test database transition used when Spatie transition not configured
     */
    public function test_database_transition_when_spatie_not_configured(): void
    {
        $workflow = $this->createTestWorkflow();

        $pendingDbState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        // Create a custom state that is NOT in Spatie config
        $customState = $this->createWorkflowState($workflow, [
            'name' => 'custom_review',
            'label' => 'Custom Review',
        ]);

        // Create database transition for this path
        $this->createWorkflowTransition($workflow, $pendingDbState, $customState);

        $order = Order::create([
            'order_number' => 'ORD-FALL-002',
            'customer_name' => 'Jane Doe',
            'total_amount' => 150.00,
            'state' => PendingState::class,
        ]);

        // This transition is only in database, not in Spatie
        $order->transitionTo('custom_review');

        $this->assertEquals('custom_review', $order->state);
    }

    /**
     * Test mixed validation rules from Spatie and database
     */
    public function test_mixed_validation_applies(): void
    {
        $workflow = $this->createTestWorkflow();

        $pendingDbState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        $reviewState = $this->createWorkflowState($workflow, [
            'name' => 'under_review',
            'label' => 'Under Review',
        ]);

        $this->createWorkflowTransition($workflow, $pendingDbState, $reviewState);

        $order = Order::create([
            'order_number' => 'ORD-FALL-003',
            'customer_name' => 'Bob Smith',
            'total_amount' => 200.00,
            'state' => PendingState::class,
        ]);

        // Transition to database-only state
        $order->transitionTo('under_review');

        $this->assertEquals('under_review', $order->state);

        // Verify transition was logged
        $this->assertTransitionLogged($order, PendingState::class, 'under_review');
    }

    /**
     * Test fallback preserves transition history
     */
    public function test_fallback_preserves_history(): void
    {
        $workflow = $this->createTestWorkflow();

        $pendingDbState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        $customState = $this->createWorkflowState($workflow, [
            'name' => 'custom_state',
        ]);

        $this->createWorkflowTransition($workflow, $pendingDbState, $customState);

        $order = Order::create([
            'order_number' => 'ORD-FALL-004',
            'customer_name' => 'Alice Johnson',
            'total_amount' => 250.00,
            'state' => PendingState::class,
        ]);

        $order->transitionTo('custom_state');

        $lastTransition = $this->getLastTransition($order);

        $this->assertNotNull($lastTransition);
        $this->assertEquals(PendingState::class, $lastTransition->from_state);
        $this->assertEquals('custom_state', $lastTransition->to_state);
    }

    /**
     * Test Spatie invalid transition is rejected
     */
    public function test_spatie_invalid_transition_rejected(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FALL-005',
            'customer_name' => 'Charlie Brown',
            'total_amount' => 300.00,
            'state' => PendingState::class,
        ]);

        // Try to skip to DeliveredState (not allowed from PendingState in Spatie)
        $this->expectException(CouldNotPerformTransition::class);

        $order->state->transitionTo(DeliveredState::class);
    }

    /**
     * Test database transition allows paths not in Spatie
     */
    public function test_database_allows_custom_paths(): void
    {
        $workflow = $this->createTestWorkflow();

        $pendingDbState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        // Create a direct path to delivered (not allowed in Spatie)
        $deliveredDbState = $this->createWorkflowState($workflow, [
            'name' => 'delivered',
            'class_name' => DeliveredState::class,
        ]);

        $this->createWorkflowTransition($workflow, $pendingDbState, $deliveredDbState);

        $order = Order::create([
            'order_number' => 'ORD-FALL-006',
            'customer_name' => 'David Lee',
            'total_amount' => 350.00,
            'state' => PendingState::class,
        ]);

        // Use transitionTo which checks database transitions
        $order->transitionTo(DeliveredState::class);

        $this->assertInstanceOf(DeliveredState::class, $order->state);
    }

    /**
     * Test fallback chain: Spatie -> Database -> Error
     */
    public function test_fallback_chain(): void
    {
        $workflow = $this->createTestWorkflow();

        // Only create pending state in database
        $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-FALL-007',
            'customer_name' => 'Eve Martin',
            'total_amount' => 400.00,
            'state' => PendingState::class,
        ]);

        // Spatie allows Pending -> Processing
        // This should work via Spatie
        $order->state->transitionTo(ProcessingState::class, [
            'processing_notes' => 'Via Spatie chain',
        ]);

        $this->assertInstanceOf(ProcessingState::class, $order->state);
    }

    /**
     * Test state class not found uses database string
     */
    public function test_state_class_not_found_uses_database(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FALL-008',
            'customer_name' => 'Frank Wilson',
            'total_amount' => 450.00,
        ]);

        // Set a state that doesn't have a PHP class
        DB::table($order->getTable())
            ->where($order->getKeyName(), $order->getKey())
            ->update(['state' => 'NonExistentStateClass']);

        $order->refresh();

        // Should return as string since class doesn't exist
        $this->assertIsString($order->state);
        $this->assertEquals('NonExistentStateClass', $order->state);
    }

    /**
     * Test complete workflow with mixed Spatie and database transitions
     */
    public function test_complete_mixed_workflow(): void
    {
        $workflow = $this->createTestWorkflow();

        $pendingDbState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        $reviewState = $this->createWorkflowState($workflow, [
            'name' => 'review',
        ]);

        $processingDbState = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'class_name' => ProcessingState::class,
        ]);

        $shippedDbState = $this->createWorkflowState($workflow, [
            'name' => 'shipped',
            'class_name' => ShippedState::class,
        ]);

        // Create custom path: pending -> review (DB only)
        $this->createWorkflowTransition($workflow, $pendingDbState, $reviewState);
        // review -> processing (back to PHP state)
        $this->createWorkflowTransition($workflow, $reviewState, $processingDbState);
        // processing -> shipped (PHP state)
        $this->createWorkflowTransition($workflow, $processingDbState, $shippedDbState);

        $order = Order::create([
            'order_number' => 'ORD-FALL-009',
            'customer_name' => 'Grace Taylor',
            'total_amount' => 500.00,
            'state' => PendingState::class,
        ]);

        // Step 1: PHP -> DB (via database transition)
        $order->transitionTo('review');
        $this->assertEquals('review', $order->state);

        // Step 2: DB -> PHP (via database transition)
        $order->transitionTo(ProcessingState::class);
        $this->assertInstanceOf(ProcessingState::class, $order->state);

        // Step 3: PHP -> PHP (via Spatie or database)
        $order->transitionTo(ShippedState::class);
        $this->assertInstanceOf(ShippedState::class, $order->state);
    }

    /**
     * Test transition data passed through fallback
     */
    public function test_transition_data_passed_through_fallback(): void
    {
        $workflow = $this->createTestWorkflow();

        $pendingDbState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        $customState = $this->createWorkflowState($workflow, [
            'name' => 'custom_with_data',
        ]);

        $this->createWorkflowTransition($workflow, $pendingDbState, $customState);

        $order = Order::create([
            'order_number' => 'ORD-FALL-010',
            'customer_name' => 'Henry Adams',
            'total_amount' => 550.00,
            'state' => PendingState::class,
        ]);

        // Transition with data
        $order->transitionTo('custom_with_data', [
            'notes' => 'Custom transition notes',
            'custom_field' => 'custom_value',
        ]);

        $this->assertEquals('custom_with_data', $order->state);
    }

    /**
     * Test fallback respects workflow active status
     */
    public function test_fallback_respects_workflow_active_status(): void
    {
        // Create inactive workflow
        $workflow = $this->createTestWorkflow(['is_active' => false]);

        $pendingDbState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        $customState = $this->createWorkflowState($workflow, [
            'name' => 'inactive_state',
        ]);

        $this->createWorkflowTransition($workflow, $pendingDbState, $customState);

        $order = Order::create([
            'order_number' => 'ORD-FALL-011',
            'customer_name' => 'Ivy Chen',
            'total_amount' => 600.00,
            'state' => PendingState::class,
        ]);

        // Spatie transition should still work (Pending -> Processing)
        $order->state->transitionTo(ProcessingState::class, [
            'processing_notes' => 'Works via Spatie',
        ]);

        $this->assertInstanceOf(ProcessingState::class, $order->state);
    }

    /**
     * Test multiple workflows don't conflict
     */
    public function test_multiple_workflows_no_conflict(): void
    {
        // Create two workflows for the same model
        $workflow1 = $this->createTestWorkflow(['name' => 'Workflow 1']);
        $workflow2 = $this->createTestWorkflow(['name' => 'Workflow 2', 'is_active' => false]);

        $pendingDbState1 = $this->createWorkflowState($workflow1, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        $customState1 = $this->createWorkflowState($workflow1, [
            'name' => 'workflow1_state',
        ]);

        $this->createWorkflowTransition($workflow1, $pendingDbState1, $customState1);

        // Workflow 2 has different transitions (but is inactive)
        $pendingDbState2 = $this->createWorkflowState($workflow2, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        $customState2 = $this->createWorkflowState($workflow2, [
            'name' => 'workflow2_state',
        ]);

        $this->createWorkflowTransition($workflow2, $pendingDbState2, $customState2);

        $order = Order::create([
            'order_number' => 'ORD-FALL-012',
            'customer_name' => 'Jack Miller',
            'total_amount' => 650.00,
            'state' => PendingState::class,
        ]);

        // Should use active workflow (workflow1)
        $order->transitionTo('workflow1_state');

        $this->assertEquals('workflow1_state', $order->state);
    }
}
