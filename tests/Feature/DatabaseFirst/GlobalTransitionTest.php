<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class GlobalTransitionTest extends TestCase
{
    public function test_global_transition_from_any_state(): void
    {
        $workflow = $this->createTestWorkflow();
        $state1 = $this->createWorkflowState($workflow, ['name' => 'state1']);
        $state2 = $this->createWorkflowState($workflow, ['name' => 'state2']);
        $cancelledState = $this->createWorkflowState($workflow, ['name' => 'cancelled']);

        // Specific transition: state1 → state2
        $this->createWorkflowTransition($workflow, $state1, $state2);

        // Global transition: any → cancelled (from_state_id = null)
        WorkflowTransition::create([
            'workflow_id' => $workflow->id,
            'from_state_id' => null,
            'to_state_id' => $cancelledState->id,
            'name' => 'cancel',
            'label' => 'Cancel',
        ]);

        // Can cancel from state1
        $order = $this->createOrder(['state' => 'state1']);
        $this->assertTrue($order->canTransitionTo('cancelled'));
        $order->transitionTo('cancelled');
        $this->assertEquals('cancelled', $order->state);

        // Can cancel from state2
        $order2 = $this->createOrder(['state' => 'state2']);
        $this->assertTrue($order2->canTransitionTo('cancelled'));
        $order2->transitionTo('cancelled');
        $this->assertEquals('cancelled', $order2->state);
    }

    public function test_specific_transition_preferred_over_global(): void
    {
        $workflow = $this->createTestWorkflow();
        $state1 = $this->createWorkflowState($workflow, ['name' => 'state1']);
        $state2 = $this->createWorkflowState($workflow, ['name' => 'state2']);

        // Specific transition: state1 → state2
        $specificTransition = $this->createWorkflowTransition($workflow, $state1, $state2, [
            'name' => 'specific_to_state2',
            'requires_confirmation' => true,
        ]);

        // Global transition: any → state2
        $globalTransition = WorkflowTransition::create([
            'workflow_id' => $workflow->id,
            'from_state_id' => null,
            'to_state_id' => $state2->id,
            'name' => 'global_to_state2',
            'label' => 'Global to State 2',
            'requires_confirmation' => false,
        ]);

        $order = $this->createOrder(['state' => 'state1']);
        $order->transitionTo('state2');

        // The specific transition should have been used (logged)
        $this->assertDatabaseHas('workflow_state_transitions', [
            'transitionable_id' => $order->id,
            'transition_id' => $specificTransition->id,
        ]);
    }

    public function test_global_transition_is_logged(): void
    {
        $workflow = $this->createTestWorkflow();
        $state1 = $this->createWorkflowState($workflow, ['name' => 'active']);
        $cancelledState = $this->createWorkflowState($workflow, ['name' => 'cancelled']);

        $globalTransition = WorkflowTransition::create([
            'workflow_id' => $workflow->id,
            'from_state_id' => null,
            'to_state_id' => $cancelledState->id,
            'name' => 'cancel',
            'label' => 'Cancel',
        ]);

        $order = $this->createOrder(['state' => 'active']);
        $order->transitionTo('cancelled');

        $this->assertDatabaseHas('workflow_state_transitions', [
            'transitionable_type' => Order::class,
            'transitionable_id' => $order->id,
            'from_state' => 'active',
            'to_state' => 'cancelled',
            'workflow_id' => $workflow->id,
            'transition_id' => $globalTransition->id,
        ]);
    }

    public function test_global_transition_available_in_get_available_transitions(): void
    {
        $workflow = $this->createTestWorkflow();
        $state1 = $this->createWorkflowState($workflow, ['name' => 'active']);
        $cancelledState = $this->createWorkflowState($workflow, ['name' => 'cancelled']);

        WorkflowTransition::create([
            'workflow_id' => $workflow->id,
            'from_state_id' => null,
            'to_state_id' => $cancelledState->id,
            'name' => 'cancel',
            'label' => 'Cancel',
        ]);

        $order = $this->createOrder(['state' => 'active']);
        $available = $order->getAvailableTransitions();

        $this->assertCount(1, $available);
        $this->assertEquals('cancel', $available->first()->name);
    }

    public function test_can_transition_to_returns_false_without_global_or_specific(): void
    {
        $workflow = $this->createTestWorkflow();
        $state1 = $this->createWorkflowState($workflow, ['name' => 'state1']);
        $this->createWorkflowState($workflow, ['name' => 'state2']);

        // No transitions defined
        $order = $this->createOrder(['state' => 'state1']);
        $this->assertFalse($order->canTransitionTo('state2'));
    }

    private function createOrder(array $data = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-GT-'.uniqid(),
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ], $data));
    }
}
