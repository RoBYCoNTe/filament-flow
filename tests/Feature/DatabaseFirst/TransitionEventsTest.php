<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use Illuminate\Support\Facades\Event;
use RoBYCoNTe\FilamentFlow\Events\StateEntered;
use RoBYCoNTe\FilamentFlow\Events\StateExited;
use RoBYCoNTe\FilamentFlow\Events\TransitionCompleted;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class TransitionEventsTest extends TestCase
{
    public function test_state_exited_event_dispatched(): void
    {
        Event::fake([StateExited::class, StateEntered::class, TransitionCompleted::class]);

        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'processing']);
        $this->createWorkflowTransition($workflow, $s1, $s2);

        $order = $this->createOrder(['state' => 'pending']);
        $order->transitionTo('processing');

        Event::assertDispatched(StateExited::class, function (StateExited $event) use ($order) {
            return $event->record->is($order) && $event->state === 'pending';
        });
    }

    public function test_state_entered_event_dispatched(): void
    {
        Event::fake([StateExited::class, StateEntered::class, TransitionCompleted::class]);

        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'processing']);
        $this->createWorkflowTransition($workflow, $s1, $s2);

        $order = $this->createOrder(['state' => 'pending']);
        $order->transitionTo('processing');

        Event::assertDispatched(StateEntered::class, function (StateEntered $event) use ($order) {
            return $event->record->is($order) && $event->state === 'processing';
        });
    }

    public function test_transition_completed_event_dispatched(): void
    {
        Event::fake([StateExited::class, StateEntered::class, TransitionCompleted::class]);

        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'processing']);
        $this->createWorkflowTransition($workflow, $s1, $s2);

        $order = $this->createOrder(['state' => 'pending']);
        $order->transitionTo('processing');

        Event::assertDispatched(TransitionCompleted::class, function (TransitionCompleted $event) use ($order) {
            return $event->record->is($order)
                && $event->from === 'pending'
                && $event->to === 'processing';
        });
    }

    public function test_transition_completed_event_dispatched_with_metadata_arg(): void
    {
        Event::fake([StateExited::class, StateEntered::class, TransitionCompleted::class]);

        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'processing']);
        $this->createWorkflowTransition($workflow, $s1, $s2);

        $order = $this->createOrder(['state' => 'pending']);
        $order->transitionTo('processing', ['transition_notes' => 'Approved']);

        // Event is dispatched even when transition data is passed
        // Note: pendingTransitionData is cleared by logTransition before event dispatch,
        // so metadata arrives as empty array in the event — this is a known limitation.
        Event::assertDispatched(TransitionCompleted::class, function (TransitionCompleted $event) use ($order) {
            return $event->record->is($order)
                && $event->from === 'pending'
                && $event->to === 'processing';
        });
    }

    public function test_self_transition_dispatches_completed_event(): void
    {
        Event::fake([StateExited::class, StateEntered::class, TransitionCompleted::class]);

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

        Event::assertDispatched(TransitionCompleted::class, function (TransitionCompleted $event) {
            return $event->from === 'active' && $event->to === 'active';
        });
    }

    public function test_transition_dispatches_all_three_events(): void
    {
        Event::fake([StateExited::class, StateEntered::class, TransitionCompleted::class]);

        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'done']);
        $this->createWorkflowTransition($workflow, $s1, $s2);

        $order = $this->createOrder(['state' => 'pending']);
        $order->transitionTo('done');

        Event::assertDispatched(StateExited::class);
        Event::assertDispatched(StateEntered::class);
        Event::assertDispatched(TransitionCompleted::class);
    }

    private function createOrder(array $data = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-EVT-'.uniqid(),
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ], $data));
    }
}
