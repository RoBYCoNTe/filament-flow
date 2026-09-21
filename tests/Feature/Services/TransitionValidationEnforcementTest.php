<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Services;

use RoBYCoNTe\FilamentFlow\Exceptions\WorkflowValidationException;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateField;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionValidationRule;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * The validation pass is part of the transition itself: every entry point
 * (form, API, console) goes through it, and it cannot be skipped by accident.
 */
class TransitionValidationEnforcementTest extends TestCase
{
    public function test_a_transition_is_refused_when_the_state_rules_do_not_hold(): void
    {
        [$order] = $this->workflow();

        try {
            $order->transitionTo('shipped');
            $this->fail('The transition should have been refused.');
        } catch (WorkflowValidationException $exception) {
            $this->assertTrue($exception->result->has('tracking_number'));
        }

        $this->assertSame('pending', $order->fresh()->state, 'The state must not change.');
        $this->assertSame(0, WorkflowStateTransition::query()->count(), 'No history row for a refused transition.');
    }

    public function test_the_exception_message_carries_the_rule_failures(): void
    {
        [$order] = $this->workflow();

        try {
            $order->transitionTo('shipped');
            $this->fail('The transition should have been refused.');
        } catch (WorkflowValidationException $exception) {
            // Not just "The given data was invalid.": the field and the message.
            $this->assertStringContainsString('tracking_number', $exception->getMessage());
            $this->assertStringContainsString('required', strtolower($exception->getMessage()));
            $this->assertSame($exception->errorSummary(), $exception->getMessage());
        }
    }

    public function test_the_transition_runs_when_the_rules_hold(): void
    {
        [$order] = $this->workflow();

        $order->update(['tracking_number' => 'TRACK-9']);

        $order->transitionTo('shipped');

        $this->assertSame('shipped', $order->fresh()->state);
    }

    public function test_the_payload_of_the_transition_is_validated_too(): void
    {
        [$order, $transition] = $this->workflow();

        WorkflowTransitionValidationRule::create([
            'transition_id' => $transition->id,
            'field_name' => 'total_amount',
            'rules' => ['gte:200'],
            'custom_message' => 'The amount must be at least 200.',
            'sort_order' => 0,
        ]);

        $order->update(['tracking_number' => 'TRACK-9']);

        try {
            $order->transitionTo('shipped');
            $this->fail('The transition should have been refused.');
        } catch (WorkflowValidationException $exception) {
            $this->assertSame(['The amount must be at least 200.'], $exception->result->messagesFor('total_amount'));
        }

        // The value arriving with the transition satisfies the rule.
        $order->transitionTo('shipped', ['total_amount' => 250]);

        $this->assertSame('shipped', $order->fresh()->state);
    }

    public function test_an_in_state_action_is_validated_the_same_way(): void
    {
        [$order, , $workflow] = $this->workflow();

        $pending = $workflow->states()->where('name', 'pending')->firstOrFail();

        $this->createWorkflowTransition($workflow, $pending, $pending, [
            'name' => 'save_draft',
            'label' => 'Save draft',
            'to_state_id' => null,
        ]);

        try {
            $order->executeAction('save_draft');
            $this->fail('The action should have been refused.');
        } catch (WorkflowValidationException) {
            $this->assertSame('pending', $order->fresh()->state);
        }

        $order->update(['tracking_number' => 'TRACK-9']);

        $order->executeAction('save_draft');

        $this->assertSame('pending', $order->fresh()->state);
    }

    public function test_force_transition_is_the_explicit_escape_hatch(): void
    {
        [$order] = $this->workflow();

        // No tracking number on the record: only an explicit force goes through.
        $order->forceTransitionTo('shipped');

        $this->assertSame('shipped', $order->fresh()->state);
    }

    public function test_validation_can_be_disabled(): void
    {
        [$order] = $this->workflow();

        config()->set('filament-flow.validation.enabled', false);

        $order->transitionTo('shipped');

        $this->assertSame('shipped', $order->fresh()->state);
    }

    // ── fixture ──────────────────────────────────────────────────────────────

    /**
     * A workflow whose `pending` state requires the tracking number.
     *
     * @return array{0: Order, 1: WorkflowTransition, 2: Workflow}
     */
    private function workflow(): array
    {
        $workflow = $this->createTestWorkflow();

        $pending = $this->createWorkflowState($workflow, ['name' => 'pending', 'is_initial' => true]);
        $shipped = $this->createWorkflowState($workflow, ['name' => 'shipped']);

        $transition = $this->createWorkflowTransition($workflow, $pending, $shipped, ['name' => 'ship']);

        WorkflowStateField::create([
            'state_id' => $pending->id,
            'field_name' => 'tracking_number',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-1',
            'tracking_number' => null,
            'customer_name' => 'Ada Lovelace',
            'customer_email' => 'ada@example.com',
            'total_amount' => 100.0,
            'state' => 'pending',
        ]);

        return [$order, $transition, $workflow];
    }
}
