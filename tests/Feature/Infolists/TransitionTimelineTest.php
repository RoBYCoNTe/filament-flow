<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Infolists;

use RoBYCoNTe\FilamentFlow\Models\WorkflowStateTransition;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Tests for TransitionTimeline component and transition history.
 *
 * Since the Filament Infolists package may not be installed,
 * we test the underlying data model and query logic.
 */
class TransitionTimelineTest extends TestCase
{
    public function test_transition_timeline_source_file_exists(): void
    {
        $this->assertFileExists(
            __DIR__.'/../../../src/Infolists/Components/TransitionTimeline.php'
        );
    }

    public function test_no_transitions_for_new_record(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-TL-001',
            'customer_name' => 'Timeline Customer',
            'total_amount' => 50.00,
            'state' => 'pending',
        ]);

        $count = WorkflowStateTransition::where('transitionable_type', Order::class)
            ->where('transitionable_id', $order->id)
            ->count();

        $this->assertEquals(0, $count);
    }

    public function test_transition_logged_after_state_change(): void
    {
        $workflow = $this->createTestWorkflow();

        $pending = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'label' => 'Pending',
            'is_initial' => true,
            'sort_order' => 0,
        ]);

        $processing = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'label' => 'Processing',
            'sort_order' => 1,
        ]);

        $transition = $this->createWorkflowTransition($workflow, $pending, $processing, [
            'name' => 'start_processing',
            'label' => 'Start Processing',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-TL-002',
            'customer_name' => 'Timeline Customer',
            'total_amount' => 75.00,
            'state' => 'pending',
        ]);

        WorkflowStateTransition::create([
            'transitionable_type' => Order::class,
            'transitionable_id' => $order->id,
            'workflow_id' => $workflow->id,
            'transition_id' => $transition->id,
            'from_state' => 'pending',
            'to_state' => 'processing',
            'from_state_label' => 'Pending',
            'to_state_label' => 'Processing',
            'is_visible' => true,
            'created_at' => now(),
        ]);

        $count = WorkflowStateTransition::where('transitionable_type', Order::class)
            ->where('transitionable_id', $order->id)
            ->count();

        $this->assertEquals(1, $count);

        $lastTransition = $this->getLastTransition($order);
        $this->assertNotNull($lastTransition);
        $this->assertEquals('pending', $lastTransition->from_state);
        $this->assertEquals('processing', $lastTransition->to_state);
    }

    public function test_transitions_ordered_by_date_desc(): void
    {
        $workflow = $this->createTestWorkflow();

        $pending = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'label' => 'Pending',
            'is_initial' => true,
            'sort_order' => 0,
        ]);

        $processing = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'label' => 'Processing',
            'sort_order' => 1,
        ]);

        $shipped = $this->createWorkflowState($workflow, [
            'name' => 'shipped',
            'label' => 'Shipped',
            'sort_order' => 2,
        ]);

        $t1 = $this->createWorkflowTransition($workflow, $pending, $processing);
        $t2 = $this->createWorkflowTransition($workflow, $processing, $shipped);

        $order = Order::create([
            'order_number' => 'ORD-TL-003',
            'customer_name' => 'Timeline Customer',
            'total_amount' => 75.00,
            'state' => 'shipped',
        ]);

        WorkflowStateTransition::create([
            'transitionable_type' => Order::class,
            'transitionable_id' => $order->id,
            'workflow_id' => $workflow->id,
            'transition_id' => $t1->id,
            'from_state' => 'pending',
            'to_state' => 'processing',
            'from_state_label' => 'Pending',
            'to_state_label' => 'Processing',
            'is_visible' => true,
            'created_at' => now()->subMinutes(10),
        ]);

        WorkflowStateTransition::create([
            'transitionable_type' => Order::class,
            'transitionable_id' => $order->id,
            'workflow_id' => $workflow->id,
            'transition_id' => $t2->id,
            'from_state' => 'processing',
            'to_state' => 'shipped',
            'from_state_label' => 'Processing',
            'to_state_label' => 'Shipped',
            'is_visible' => true,
            'created_at' => now(),
        ]);

        $transitions = WorkflowStateTransition::where('transitionable_type', Order::class)
            ->where('transitionable_id', $order->id)
            ->orderByDesc('created_at')
            ->get();

        $this->assertCount(2, $transitions);
        $this->assertEquals('shipped', $transitions[0]->to_state);
        $this->assertEquals('processing', $transitions[1]->to_state);
    }

    public function test_total_transition_count(): void
    {
        $workflow = $this->createTestWorkflow();

        $pending = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'label' => 'Pending',
            'is_initial' => true,
            'sort_order' => 0,
        ]);

        $processing = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'label' => 'Processing',
            'sort_order' => 1,
        ]);

        $transition = $this->createWorkflowTransition($workflow, $pending, $processing);

        $order = Order::create([
            'order_number' => 'ORD-TL-004',
            'customer_name' => 'Test',
            'total_amount' => 50.00,
            'state' => 'processing',
        ]);

        for ($i = 0; $i < 3; $i++) {
            WorkflowStateTransition::create([
                'transitionable_type' => Order::class,
                'transitionable_id' => $order->id,
                'workflow_id' => $workflow->id,
                'transition_id' => $transition->id,
                'from_state' => 'pending',
                'to_state' => 'processing',
                'from_state_label' => 'Pending',
                'to_state_label' => 'Processing',
                'is_visible' => true,
                'created_at' => now()->subMinutes($i),
            ]);
        }

        $count = WorkflowStateTransition::where('transitionable_type', Order::class)
            ->where('transitionable_id', $order->id)
            ->visible()
            ->count();

        $this->assertEquals(3, $count);
    }

    public function test_visible_scope_filters_hidden_transitions(): void
    {
        $workflow = $this->createTestWorkflow();

        $pending = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'label' => 'Pending',
            'is_initial' => true,
            'sort_order' => 0,
        ]);

        $processing = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'label' => 'Processing',
            'sort_order' => 1,
        ]);

        $transition = $this->createWorkflowTransition($workflow, $pending, $processing);

        $order = Order::create([
            'order_number' => 'ORD-TL-005',
            'customer_name' => 'Test',
            'total_amount' => 50.00,
            'state' => 'processing',
        ]);

        WorkflowStateTransition::create([
            'transitionable_type' => Order::class,
            'transitionable_id' => $order->id,
            'workflow_id' => $workflow->id,
            'transition_id' => $transition->id,
            'from_state' => 'pending',
            'to_state' => 'processing',
            'from_state_label' => 'Pending',
            'to_state_label' => 'Processing',
            'is_visible' => true,
            'created_at' => now(),
        ]);

        WorkflowStateTransition::create([
            'transitionable_type' => Order::class,
            'transitionable_id' => $order->id,
            'workflow_id' => $workflow->id,
            'transition_id' => $transition->id,
            'from_state' => 'processing',
            'to_state' => 'pending',
            'from_state_label' => 'Processing',
            'to_state_label' => 'Pending',
            'is_visible' => false,
            'created_at' => now(),
        ]);

        $visibleCount = WorkflowStateTransition::where('transitionable_type', Order::class)
            ->where('transitionable_id', $order->id)
            ->visible()
            ->count();

        $totalCount = WorkflowStateTransition::where('transitionable_type', Order::class)
            ->where('transitionable_id', $order->id)
            ->count();

        $this->assertEquals(1, $visibleCount);
        $this->assertEquals(2, $totalCount);
    }
}
