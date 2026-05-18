<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Models\WorkflowStateTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionMetadata;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class TransitionAuditTrailTest extends TestCase
{
    public function test_transition_creates_before_snapshot(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'done']);
        $this->createWorkflowTransition($workflow, $s1, $s2);

        $order = $this->createOrder(['state' => 'pending', 'notes' => 'Before value']);
        $order->transitionTo('done');

        $history = $this->getLastTransition($order);
        $this->assertNotNull($history);

        $beforeSnapshot = $history->snapshotBefore;
        $this->assertNotNull($beforeSnapshot);
        $this->assertEquals('before', $beforeSnapshot->snapshot_type);
        $this->assertEquals('Before value', $beforeSnapshot->record_data['notes']);
    }

    public function test_transition_creates_after_snapshot(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'done']);
        $this->createWorkflowTransition($workflow, $s1, $s2);

        $order = $this->createOrder(['state' => 'pending']);
        $order->transitionTo('done');

        $history = $this->getLastTransition($order);
        $afterSnapshot = $history->snapshotAfter;
        $this->assertNotNull($afterSnapshot);
        $this->assertEquals('after', $afterSnapshot->snapshot_type);
    }

    public function test_transition_stores_metadata(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'done']);
        $this->createWorkflowTransition($workflow, $s1, $s2);

        $order = $this->createOrder(['state' => 'pending']);
        $order->transitionTo('done', ['transition_notes' => 'Approved by manager']);

        $history = $this->getLastTransition($order);
        $this->assertTrue($history->has_metadata);

        $metadata = $history->metadata;
        $this->assertNotNull($metadata);
        $this->assertInstanceOf(WorkflowTransitionMetadata::class, $metadata);
        $this->assertEquals('Approved by manager', $metadata->form_data['transition_notes']);
    }

    public function test_transition_without_data_has_no_metadata(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'done']);
        $this->createWorkflowTransition($workflow, $s1, $s2);

        $order = $this->createOrder(['state' => 'pending']);
        $order->transitionTo('done');

        $history = $this->getLastTransition($order);
        $this->assertFalse($history->has_metadata);
        $this->assertNull($history->metadata);
    }

    public function test_transition_history_scopes(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'done']);
        $this->createWorkflowTransition($workflow, $s1, $s2);

        $order = $this->createOrder(['state' => 'pending']);
        $order->transitionTo('done');

        // scopeForRecord
        $records = WorkflowStateTransition::forRecord($order)->get();
        $this->assertCount(1, $records);

        // scopeToState
        $toState = WorkflowStateTransition::toState('done')->get();
        $this->assertCount(1, $toState);

        // scopeBetweenDates
        $inRange = WorkflowStateTransition::betweenDates(
            now()->subDay(),
            now()->addDay()
        )->get();
        $this->assertCount(1, $inRange);
    }

    public function test_is_action_method(): void
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
        $order->executeAction('add_note');

        $history = $this->getLastTransition($order);
        $this->assertTrue($history->isAction());
    }

    public function test_snapshots_relationship(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'done']);
        $this->createWorkflowTransition($workflow, $s1, $s2);

        $order = $this->createOrder(['state' => 'pending']);
        $order->transitionTo('done');

        $history = $this->getLastTransition($order);
        $snapshots = $history->snapshots;

        // before + after = 2 snapshots
        $this->assertCount(2, $snapshots);
    }

    private function createOrder(array $data = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-AUDIT-'.uniqid(),
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ], $data));
    }
}
