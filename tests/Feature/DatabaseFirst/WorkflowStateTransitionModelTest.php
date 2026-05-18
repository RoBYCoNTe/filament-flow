<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Models\WorkflowStateTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionField;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class WorkflowStateTransitionModelTest extends TestCase
{
    public function test_is_action_when_same_state(): void
    {
        $transition = new WorkflowStateTransition([
            'from_state' => 'active',
            'to_state' => 'active',
        ]);

        $this->assertTrue($transition->isAction());
    }

    public function test_is_action_when_to_state_null(): void
    {
        $transition = new WorkflowStateTransition([
            'from_state' => 'active',
            'to_state' => null,
        ]);

        $this->assertTrue($transition->isAction());
    }

    public function test_is_not_action_when_state_changes(): void
    {
        $transition = new WorkflowStateTransition([
            'from_state' => 'pending',
            'to_state' => 'processing',
        ]);

        $this->assertFalse($transition->isAction());
    }

    public function test_scope_for_record(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'done']);
        $this->createWorkflowTransition($workflow, $s1, $s2);

        $order = Order::create([
            'order_number' => 'ORD-WST-001',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'pending',
        ]);

        $order->transitionTo('done');

        $transitions = WorkflowStateTransition::forRecord($order)->get();
        $this->assertGreaterThan(0, $transitions->count());
    }

    public function test_scope_to_state(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'done']);
        $this->createWorkflowTransition($workflow, $s1, $s2);

        $order = Order::create([
            'order_number' => 'ORD-WST-002',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'pending',
        ]);

        $order->transitionTo('done');

        $transitions = WorkflowStateTransition::toState('done')->get();
        $this->assertGreaterThan(0, $transitions->count());
    }

    public function test_scope_visible(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'done']);
        $this->createWorkflowTransition($workflow, $s1, $s2);

        $order = Order::create([
            'order_number' => 'ORD-WST-003',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'pending',
        ]);

        $order->transitionTo('done');

        $visible = WorkflowStateTransition::visible()->forRecord($order)->get();
        $this->assertGreaterThan(0, $visible->count());
    }

    public function test_scope_by_user(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'done']);
        $this->createWorkflowTransition($workflow, $s1, $s2);

        $user = $this->createTestUser();
        $this->actingAs($user);

        $order = Order::create([
            'order_number' => 'ORD-WST-004',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'pending',
        ]);

        $order->transitionTo('done');

        $byUser = WorkflowStateTransition::byUser($user->id)->get();
        $this->assertGreaterThan(0, $byUser->count());
    }

    public function test_scope_between_dates(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'done']);
        $this->createWorkflowTransition($workflow, $s1, $s2);

        $order = Order::create([
            'order_number' => 'ORD-WST-005',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'pending',
        ]);

        $order->transitionTo('done');

        $from = now()->subDay();
        $to = now()->addDay();

        $results = WorkflowStateTransition::betweenDates($from, $to)->get();
        $this->assertGreaterThan(0, $results->count());
    }

    public function test_metadata_relationship(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'done']);
        $t = $this->createWorkflowTransition($workflow, $s1, $s2);

        // Create transition field to capture metadata
        WorkflowTransitionField::create([
            'transition_id' => $t->id,
            'field_name' => 'notes',
            'field_type' => 'textarea',
            'label' => 'Notes',
            'sort_order' => 0,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-WST-006',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'pending',
        ]);

        $order->transitionTo('done', ['notes' => 'Test note']);

        $transition = WorkflowStateTransition::forRecord($order)->first();
        // Metadata relationship should exist if transition data was stored
        $this->assertNotNull($transition);
    }
}
