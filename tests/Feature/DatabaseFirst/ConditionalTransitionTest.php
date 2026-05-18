<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

class ConditionalTransitionTest extends TestCase
{
    public function test_transition_allowed_when_conditions_pass(): void
    {
        $workflow = $this->createTestWorkflow();
        $state1 = $this->createWorkflowState($workflow, ['name' => 'state1']);
        $state2 = $this->createWorkflowState($workflow, ['name' => 'state2']);

        $this->createWorkflowTransition($workflow, $state1, $state2, [
            'name' => 'conditional_transition',
            'conditions' => [
                ['field' => 'total_amount', 'operator' => '>=', 'value' => 100],
            ],
        ]);

        $order = $this->createOrder(['state' => 'state1', 'total_amount' => 150]);

        $this->assertTrue($order->canTransitionTo('state2'));
        $order->transitionTo('state2');
        $this->assertEquals('state2', $order->state);
    }

    public function test_transition_blocked_when_conditions_fail(): void
    {
        $workflow = $this->createTestWorkflow();
        $state1 = $this->createWorkflowState($workflow, ['name' => 'state1']);
        $state2 = $this->createWorkflowState($workflow, ['name' => 'state2']);

        $this->createWorkflowTransition($workflow, $state1, $state2, [
            'name' => 'conditional_transition',
            'conditions' => [
                ['field' => 'total_amount', 'operator' => '>=', 'value' => 200],
            ],
        ]);

        $order = $this->createOrder(['state' => 'state1', 'total_amount' => 50]);

        $this->assertFalse($order->canTransitionTo('state2'));
    }

    public function test_transition_rejected_when_conditions_fail(): void
    {
        $workflow = $this->createTestWorkflow();
        $state1 = $this->createWorkflowState($workflow, ['name' => 'state1']);
        $state2 = $this->createWorkflowState($workflow, ['name' => 'state2']);

        $this->createWorkflowTransition($workflow, $state1, $state2, [
            'name' => 'conditional_transition',
            'conditions' => [
                ['field' => 'total_amount', 'operator' => '>=', 'value' => 200],
            ],
        ]);

        $order = $this->createOrder(['state' => 'state1', 'total_amount' => 50]);

        $this->expectException(TransitionNotFound::class);
        $order->transitionTo('state2');
    }

    public function test_transition_with_multiple_conditions(): void
    {
        $workflow = $this->createTestWorkflow();
        $state1 = $this->createWorkflowState($workflow, ['name' => 'state1']);
        $state2 = $this->createWorkflowState($workflow, ['name' => 'state2']);

        $this->createWorkflowTransition($workflow, $state1, $state2, [
            'name' => 'multi_condition',
            'conditions' => [
                ['field' => 'total_amount', 'operator' => '>=', 'value' => 100],
                ['field' => 'customer_name', 'operator' => '!=', 'value' => ''],
            ],
        ]);

        // Both conditions met
        $order = $this->createOrder(['state' => 'state1', 'total_amount' => 150, 'customer_name' => 'John']);
        $this->assertTrue($order->canTransitionTo('state2'));

        // First condition fails
        $order2 = $this->createOrder(['state' => 'state1', 'total_amount' => 50, 'customer_name' => 'John']);
        $this->assertFalse($order2->canTransitionTo('state2'));
    }

    public function test_conditional_transition_filtered_in_get_available_transitions(): void
    {
        $workflow = $this->createTestWorkflow();
        $state1 = $this->createWorkflowState($workflow, ['name' => 'state1']);
        $state2 = $this->createWorkflowState($workflow, ['name' => 'state2']);
        $state3 = $this->createWorkflowState($workflow, ['name' => 'state3']);

        // Unconditional transition
        $this->createWorkflowTransition($workflow, $state1, $state2, [
            'name' => 'to_state2',
        ]);

        // Conditional transition (will fail for low amount)
        $this->createWorkflowTransition($workflow, $state1, $state3, [
            'name' => 'to_state3',
            'conditions' => [
                ['field' => 'total_amount', 'operator' => '>=', 'value' => 500],
            ],
        ]);

        // Low amount → only unconditional transition available
        $order = $this->createOrder(['state' => 'state1', 'total_amount' => 100]);
        $available = $order->getAvailableTransitions();
        $this->assertCount(1, $available);
        $this->assertEquals('to_state2', $available->first()->name);

        // High amount → both transitions available
        $order2 = $this->createOrder(['state' => 'state1', 'total_amount' => 1000]);
        $available2 = $order2->getAvailableTransitions();
        $this->assertCount(2, $available2);
    }

    public function test_transition_with_in_condition(): void
    {
        $workflow = $this->createTestWorkflow();
        $state1 = $this->createWorkflowState($workflow, ['name' => 'state1']);
        $state2 = $this->createWorkflowState($workflow, ['name' => 'state2']);

        $this->createWorkflowTransition($workflow, $state1, $state2, [
            'name' => 'type_specific',
            'conditions' => [
                ['field' => 'carrier', 'operator' => 'in', 'value' => ['FedEx', 'UPS']],
            ],
        ]);

        $order = $this->createOrder(['state' => 'state1', 'carrier' => 'FedEx']);
        $this->assertTrue($order->canTransitionTo('state2'));

        $order2 = $this->createOrder(['state' => 'state1', 'carrier' => 'DHL']);
        $this->assertFalse($order2->canTransitionTo('state2'));
    }

    public function test_transition_without_conditions_always_passes(): void
    {
        $workflow = $this->createTestWorkflow();
        $state1 = $this->createWorkflowState($workflow, ['name' => 'state1']);
        $state2 = $this->createWorkflowState($workflow, ['name' => 'state2']);

        $this->createWorkflowTransition($workflow, $state1, $state2);

        $order = $this->createOrder(['state' => 'state1']);
        $this->assertTrue($order->canTransitionTo('state2'));
    }

    private function createOrder(array $data = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-CT-'.uniqid(),
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ], $data));
    }
}
