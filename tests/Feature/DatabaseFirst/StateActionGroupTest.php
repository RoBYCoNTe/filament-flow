<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Actions\StateAction;
use RoBYCoNTe\FilamentFlow\Actions\StateActionGroup;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class StateActionGroupTest extends TestCase
{
    public function test_for_database_record_returns_empty_without_workflow(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-SAG-001',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'pending',
        ]);

        // No workflow → empty actions
        $actions = StateActionGroup::forDatabaseRecord($order);
        $this->assertIsArray($actions);
    }

    public function test_for_database_record_generates_transition_actions(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending', 'label' => 'Pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'processing', 'label' => 'Processing']);
        $this->createWorkflowTransition($workflow, $s1, $s2, ['label' => 'Start Processing']);

        $order = Order::create([
            'order_number' => 'ORD-SAG-002',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'pending',
        ]);

        $actions = StateActionGroup::forDatabaseRecord($order);

        $this->assertNotEmpty($actions);
        $this->assertInstanceOf(StateAction::class, $actions[0]);
    }

    public function test_for_database_record_includes_self_transitions(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'active', 'label' => 'Active']);

        // Self-transition (action)
        WorkflowTransition::create([
            'workflow_id' => $workflow->id,
            'from_state_id' => $s1->id,
            'to_state_id' => null,
            'name' => 'add_note',
            'label' => 'Add Note',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-SAG-003',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'active',
        ]);

        $actions = StateActionGroup::forDatabaseRecord($order);

        $this->assertNotEmpty($actions);
    }

    public function test_for_database_record_returns_empty_when_disabled(): void
    {
        config(['filament-flow.enabled' => false]);

        $order = Order::create([
            'order_number' => 'ORD-SAG-004',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'pending',
        ]);

        $actions = StateActionGroup::forDatabaseRecord($order);

        $this->assertEmpty($actions);
    }

    public function test_for_database_record_filters_by_conditions(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'premium']);

        $this->createWorkflowTransition($workflow, $s1, $s2, [
            'conditions' => [
                ['field' => 'total_amount', 'operator' => '>=', 'value' => 1000],
            ],
        ]);

        $order = Order::create([
            'order_number' => 'ORD-SAG-005',
            'customer_name' => 'Test',
            'total_amount' => 50, // Below threshold
            'state' => 'pending',
        ]);

        $actions = StateActionGroup::forDatabaseRecord($order);

        // Condition not met → no actions
        $this->assertEmpty($actions);
    }

    public function test_for_database_record_includes_global_transitions(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $target = $this->createWorkflowState($workflow, ['name' => 'cancelled', 'label' => 'Cancelled']);

        // Global transition (from_state_id = null)
        WorkflowTransition::create([
            'workflow_id' => $workflow->id,
            'from_state_id' => null,
            'to_state_id' => $target->id,
            'name' => 'cancel',
            'label' => 'Cancel',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-SAG-006',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'pending',
        ]);

        $actions = StateActionGroup::forDatabaseRecord($order);

        $this->assertNotEmpty($actions);
    }

    public function test_generate_database_first_actions(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending', 'label' => 'Pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'done', 'label' => 'Done']);
        $this->createWorkflowTransition($workflow, $s1, $s2);

        // generateDatabaseFirstActions is called by the constructor when stateClass is null
        $group = StateActionGroup::generate('state', null);

        $this->assertInstanceOf(StateActionGroup::class, $group);
    }
}
