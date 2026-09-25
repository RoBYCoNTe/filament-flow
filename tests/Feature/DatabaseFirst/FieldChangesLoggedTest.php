<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Models\WorkflowStateTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionField;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * The delta a transition leaves in the history: which fields moved, from which value to
 * which — the difference the timeline reads instead of the whole form.
 */
class FieldChangesLoggedTest extends TestCase
{
    public function test_an_action_records_the_field_it_moved(): void
    {
        config(['filament-flow.field_changes.attribute' => 'notes']);

        $order = $this->annotatedOrder('Before annotation');
        $order->executeAction('annotate', ['processing_notes' => 'After annotation']);

        $history = $this->getLastTransition($order);

        $this->assertNotNull($history);
        $this->assertTrue($history->has_metadata);
        $this->assertSame(
            ['notes' => ['from' => 'Before annotation', 'to' => 'After annotation']],
            $history->metadata->field_changes,
        );
    }

    public function test_a_map_is_recorded_leaf_by_leaf_and_the_untouched_leaves_are_left_out(): void
    {
        config(['filament-flow.field_changes.attribute' => 'form_data']);

        $order = $this->annotatedOrder('Before');

        $order->form_data = [
            'applicant' => ['first_name' => 'Ada', 'last_name' => 'Lovelace'],
            'note' => 'same',
        ];
        $order->save();

        $order->executeAction('annotate', [
            'payload' => [
                'applicant' => ['first_name' => 'Ada Lovelace', 'last_name' => 'Lovelace'],
                'note' => 'same',
            ],
        ]);

        $history = $this->getLastTransition($order);

        $this->assertNotNull($history);
        $this->assertSame(
            ['applicant.first_name' => ['from' => 'Ada', 'to' => 'Ada Lovelace']],
            $history->metadata->field_changes,
        );
    }

    public function test_a_transition_that_moved_nothing_records_no_metadata(): void
    {
        $order = $this->annotatedOrder('Same');

        // The action carries no data and writes nothing: there is no delta to record.
        $order->executeAction('annotate');

        $history = $this->getLastTransition($order);

        $this->assertNotNull($history);
        $this->assertFalse($history->has_metadata);
        $this->assertNull($history->metadata);
    }

    public function test_a_transition_that_moved_nothing_says_so(): void
    {
        config(['filament-flow.field_changes.attribute' => 'notes']);

        $order = $this->annotatedOrder('Same annotation');

        // The action is given the same words the record already carries: the engine compared,
        // and the answer is "nothing moved" — not "nobody looked".
        $order->executeAction('annotate', ['processing_notes' => 'Same annotation']);

        $history = $this->getLastTransition($order);

        $this->assertNotNull($history);
        $this->assertTrue($history->has_metadata);
        $this->assertSame([], $history->metadata->field_changes);
    }

    public function test_the_recording_can_be_turned_off(): void
    {
        config([
            'filament-flow.field_changes.attribute' => 'notes',
            'filament-flow.field_changes.enabled' => false,
        ]);

        $order = $this->annotatedOrder('Before annotation');
        $order->executeAction('annotate', ['processing_notes' => 'After annotation']);

        $history = $this->getLastTransition($order);

        $this->assertNotNull($history);
        $this->assertNull($history->metadata?->field_changes);
    }

    public function test_an_ignored_path_is_left_out_of_the_delta(): void
    {
        config([
            'filament-flow.field_changes.attribute' => 'form_data',
            'filament-flow.field_changes.ignore' => ['applicant.*'],
        ]);

        $order = $this->annotatedOrder('Before');

        $order->form_data = ['applicant' => ['first_name' => 'Ada'], 'note' => 'old'];
        $order->save();

        $order->executeAction('annotate', [
            'payload' => ['applicant' => ['first_name' => 'Grace'], 'note' => 'new'],
        ]);

        $history = $this->getLastTransition($order);

        $this->assertSame(['note' => ['from' => 'old', 'to' => 'new']], $history->metadata->field_changes);
    }

    /**
     * An order whose history records the fields of the `form_data` attribute, with an action
     * (`annotate`) that writes either the payload onto the record or the named column.
     */
    private function annotatedOrder(string $notes): Order
    {
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, ['name' => 'active']);

        $action = WorkflowTransition::create([
            'workflow_id' => $workflow->id,
            'from_state_id' => null,
            'to_state_id' => null,
            'name' => 'annotate',
            'label' => 'Annotate',
        ]);

        WorkflowTransitionField::create([
            'transition_id' => $action->id,
            'field_name' => 'processing_notes',
            'field_type' => 'textarea',
            'label' => 'Notes',
            'mapping_type' => 'direct',
            'model_attribute' => 'notes',
            'sort_order' => 0,
            'is_required' => false,
            'save_to_model' => true,
        ]);

        WorkflowTransitionField::create([
            'transition_id' => $action->id,
            'field_name' => 'payload',
            'field_type' => 'textarea',
            'label' => 'Payload',
            'mapping_type' => 'direct',
            'model_attribute' => 'form_data',
            'sort_order' => 1,
            'is_required' => false,
            'save_to_model' => true,
        ]);

        return $this->createOrder(['state' => 'active', 'notes' => $notes]);
    }

    public function test_a_transition_that_writes_after_being_logged_still_records_its_delta(): void
    {
        config(['filament-flow.field_changes.payload' => true]);

        $workflow = $this->createTestWorkflow();
        $pending = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $done = $this->createWorkflowState($workflow, ['name' => 'done']);
        $this->createWorkflowTransition($workflow, $pending, $done);

        // The host writes the values of a transition **after** it (a refused transition must not
        // leave the values of an attempt behind): at log time the record still holds the old
        // ones, and the payload is the only place the delta exists.
        $order = $this->createOrder([
            'state' => 'pending',
            'form_data' => ['applicant' => ['first_name' => 'Ada'], 'note' => 'same'],
        ]);

        $order->transitionTo('done', [
            'applicant' => ['first_name' => 'Ada Lovelace'],
            'note' => 'same',
        ]);

        $history = $this->getLastTransition($order);

        $this->assertSame(
            ['applicant.first_name' => ['from' => 'Ada', 'to' => 'Ada Lovelace']],
            $history->metadata->field_changes,
        );
    }

    public function test_a_host_that_saves_before_the_transition_still_records_the_delta(): void
    {
        $order = $this->orderInState('pending');

        // A host may write the values itself, before asking for the transition — a refused
        // transition must not leave the values of an attempt behind. The record then already
        // holds the new values when the engine logs, and nothing is left to compare.
        $order->form_data = ['applicant' => ['first_name' => 'Ada Lovelace']];
        $order->save();

        $order->transitionTo('done');

        $this->assertNull(
            $this->getLastTransition($order)->metadata,
            'Senza sapere cosa c\'era prima, il motore non ha nulla da confrontare.',
        );

        // Told what the record held a moment ago, the history says what moved.
        $second = $this->orderInState('pending', ['applicant' => ['first_name' => 'Ada']]);
        $second->form_data = ['applicant' => ['first_name' => 'Ada Lovelace']];
        $second->save();

        $second->withFieldValuesBefore(['form_data' => ['applicant' => ['first_name' => 'Ada']]]);
        $second->transitionTo('done');

        $this->assertSame(
            ['applicant.first_name' => ['from' => 'Ada', 'to' => 'Ada Lovelace']],
            $this->getLastTransition($second)->metadata->field_changes,
        );
    }

    /** @param array<string,mixed> $formData */
    private function orderInState(string $state, array $formData = []): Order
    {
        $workflow = $this->createTestWorkflow();
        $pending = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $done = $this->createWorkflowState($workflow, ['name' => 'done']);
        $this->createWorkflowTransition($workflow, $pending, $done);

        return $this->createOrder([
            'state' => $state,
            'form_data' => array_merge(['applicant' => ['first_name' => 'Ada']], $formData),
        ]);
    }

    /** @param array<string,mixed> $data */
    private function createOrder(array $data = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-CHANGES-'.uniqid(),
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ], $data));
    }

    public function test_the_state_change_itself_is_not_a_field_change(): void
    {
        $workflow = $this->createTestWorkflow();
        $pending = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $done = $this->createWorkflowState($workflow, ['name' => 'done']);
        $this->createWorkflowTransition($workflow, $pending, $done);

        $order = $this->createOrder(['state' => 'pending', 'form_data' => ['note' => 'same']]);
        $order->transitionTo('done');

        $history = WorkflowStateTransition::query()
            ->where('transitionable_type', Order::class)
            ->where('transitionable_id', $order->id)
            ->latest('created_at')
            ->first();

        // The state is the subject of the entry, not one of its fields.
        $this->assertNull($history->metadata?->field_changes);
        $this->assertSame('pending', $history->from_state);
        $this->assertSame('done', $history->to_state);
    }
}
