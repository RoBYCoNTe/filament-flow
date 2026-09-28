<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Services;

use RoBYCoNTe\FilamentFlow\Definition\Enums\ValidationLevel;
use RoBYCoNTe\FilamentFlow\Exceptions\WorkflowValidationException;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateField;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * How much a transition is validated is declared by the workflow itself: a
 * "save and continue later" step accepts whatever was typed so far, the
 * transitions that move the record on enforce every rule.
 */
class TransitionValidationLevelTest extends TestCase
{
    public function test_a_transition_without_validation_saves_an_incomplete_record(): void
    {
        [$order] = $this->workflow(ValidationLevel::None);

        // The tracking number is required by the state, and it is missing.
        $order->transitionTo('shipped');

        $this->assertSame('shipped', $order->fresh()->state, 'A save cannot be blocked by the rules.');
    }

    public function test_a_semantic_level_does_not_enforce_the_workflow_rules(): void
    {
        [$order] = $this->workflow(ValidationLevel::Semantic);

        $order->transitionTo('shipped');

        $this->assertSame('shipped', $order->fresh()->state);
    }

    public function test_the_full_level_enforces_them(): void
    {
        [$order] = $this->workflow(ValidationLevel::Full);

        try {
            $order->transitionTo('shipped');
            $this->fail('The transition had to be refused.');
        } catch (WorkflowValidationException $exception) {
            $this->assertTrue($exception->result->has('tracking_number'));
        }

        $this->assertSame('pending', $order->fresh()->state);
    }

    public function test_the_level_is_exported_with_the_transition(): void
    {
        $workflow = $this->createTestWorkflow();
        $from = $this->createWorkflowState($workflow, ['name' => 'pending', 'is_initial' => true]);
        $to = $this->createWorkflowState($workflow, ['name' => 'shipped']);

        $transition = $this->createWorkflowTransition($workflow, $from, $to, ['name' => 'ship']);

        $this->assertSame(ValidationLevel::Full, $transition->validationLevel(), 'The default is "every rule".');
    }

    // ── fixture ──────────────────────────────────────────────────────────────

    /** @return array{0: Order} */
    private function workflow(ValidationLevel $level): array
    {
        $workflow = $this->createTestWorkflow();

        $pending = $this->createWorkflowState($workflow, ['name' => 'pending', 'is_initial' => true]);
        $shipped = $this->createWorkflowState($workflow, ['name' => 'shipped']);

        $transition = $this->createWorkflowTransition($workflow, $pending, $shipped, ['name' => 'ship']);
        $transition->update(['validation_level' => $level->value]);

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

        return [$order];
    }
}
