<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionSideEffect;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class SelfTransitionTest extends TestCase
{
    public function test_execute_action_does_not_change_state(): void
    {
        $workflow = $this->createTestWorkflow();
        $activeState = $this->createWorkflowState($workflow, ['name' => 'active']);

        // Self-transition (action): to_state_id = null
        WorkflowTransition::create([
            'workflow_id' => $workflow->id,
            'from_state_id' => null,
            'to_state_id' => null,
            'name' => 'add_note',
            'label' => 'Add Note',
        ]);

        $order = $this->createOrder(['state' => 'active']);
        $order->executeAction('add_note');

        $this->assertEquals('active', $order->state);
    }

    public function test_execute_action_logs_transition(): void
    {
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, ['name' => 'active']);

        $action = WorkflowTransition::create([
            'workflow_id' => $workflow->id,
            'from_state_id' => null,
            'to_state_id' => null,
            'name' => 'add_note',
            'label' => 'Add Note',
        ]);

        $order = $this->createOrder(['state' => 'active']);
        $order->executeAction('add_note');

        $this->assertDatabaseHas('workflow_state_transitions', [
            'transitionable_type' => Order::class,
            'transitionable_id' => $order->id,
            'from_state' => 'active',
            'to_state' => 'active', // Same state
            'transition_id' => $action->id,
        ]);
    }

    public function test_execute_action_executes_side_effects(): void
    {
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, ['name' => 'active']);

        $action = WorkflowTransition::create([
            'workflow_id' => $workflow->id,
            'from_state_id' => null,
            'to_state_id' => null,
            'name' => 'mark_processed',
            'label' => 'Mark Processed',
        ]);

        WorkflowTransitionSideEffect::create([
            'transition_id' => $action->id,
            'effect_type' => 'set_timestamp',
            'field_name' => 'processed_at',
            'value_expression' => 'now',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $order = $this->createOrder(['state' => 'active', 'processed_at' => null]);
        $order->executeAction('mark_processed');

        $order->refresh();
        $this->assertNotNull($order->processed_at);
        $this->assertEquals('active', $order->state);
    }

    public function test_execute_action_with_data(): void
    {
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, ['name' => 'active']);

        WorkflowTransition::create([
            'workflow_id' => $workflow->id,
            'from_state_id' => null,
            'to_state_id' => null,
            'name' => 'add_note',
            'label' => 'Add Note',
        ]);

        $order = $this->createOrder(['state' => 'active']);
        $order->executeAction('add_note', ['transition_notes' => 'This is a note']);

        // The note should be in the transition log
        $lastTransition = $this->getLastTransition($order);
        $this->assertNotNull($lastTransition);
        $this->assertEquals('This is a note', $lastTransition->notes);
    }

    public function test_get_available_actions(): void
    {
        $workflow = $this->createTestWorkflow();
        $activeState = $this->createWorkflowState($workflow, ['name' => 'active']);

        // Global action (from any state)
        WorkflowTransition::create([
            'workflow_id' => $workflow->id,
            'from_state_id' => null,
            'to_state_id' => null,
            'name' => 'add_note',
            'label' => 'Add Note',
        ]);

        // State-specific action
        WorkflowTransition::create([
            'workflow_id' => $workflow->id,
            'from_state_id' => $activeState->id,
            'to_state_id' => null,
            'name' => 'request_review',
            'label' => 'Request Review',
        ]);

        $order = $this->createOrder(['state' => 'active']);
        $actions = $order->getAvailableActions();

        $this->assertCount(2, $actions);
        $names = $actions->pluck('name')->toArray();
        $this->assertContains('add_note', $names);
        $this->assertContains('request_review', $names);
    }

    public function test_state_specific_action_not_available_from_other_state(): void
    {
        $workflow = $this->createTestWorkflow();
        $activeState = $this->createWorkflowState($workflow, ['name' => 'active']);
        $this->createWorkflowState($workflow, ['name' => 'completed']);

        // State-specific action only for 'active'
        WorkflowTransition::create([
            'workflow_id' => $workflow->id,
            'from_state_id' => $activeState->id,
            'to_state_id' => null,
            'name' => 'request_review',
            'label' => 'Request Review',
        ]);

        $order = $this->createOrder(['state' => 'completed']);
        $actions = $order->getAvailableActions();

        $this->assertCount(0, $actions);
    }

    public function test_execute_action_throws_if_not_found(): void
    {
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, ['name' => 'active']);

        $order = $this->createOrder(['state' => 'active']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Action 'nonexistent' not found for current state.");

        $order->executeAction('nonexistent');
    }

    public function test_actions_are_separate_from_transitions(): void
    {
        $workflow = $this->createTestWorkflow();
        $state1 = $this->createWorkflowState($workflow, ['name' => 'state1']);
        $state2 = $this->createWorkflowState($workflow, ['name' => 'state2']);

        // Regular transition
        $this->createWorkflowTransition($workflow, $state1, $state2);

        // Action
        WorkflowTransition::create([
            'workflow_id' => $workflow->id,
            'from_state_id' => null,
            'to_state_id' => null,
            'name' => 'add_note',
            'label' => 'Add Note',
        ]);

        $order = $this->createOrder(['state' => 'state1']);

        // getAvailableTransitions should only return state-changing transitions
        $transitions = $order->getAvailableTransitions();
        $this->assertCount(1, $transitions);
        $this->assertEquals('state1_to_state2', $transitions->first()->name);

        // getAvailableActions should only return self-transitions
        $actions = $order->getAvailableActions();
        $this->assertCount(1, $actions);
        $this->assertEquals('add_note', $actions->first()->name);
    }

    private function createOrder(array $data = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-ST-'.uniqid(),
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ], $data));
    }
}
