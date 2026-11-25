<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionValidationRule;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * The fields a transition asks for are the rules it declares, in the order it declares them: the
 * order of a form is not left to the order the database keeps its rows in, which changes as they are
 * updated.
 */
class TransitionValidationRulesOrderTest extends TestCase
{
    private function transition(): WorkflowTransition
    {
        $workflow = $this->createTestWorkflow();
        $from = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $to = $this->createWorkflowState($workflow, ['name' => 'done']);

        return $this->createWorkflowTransition($workflow, $from, $to);
    }

    private function rule(WorkflowTransition $transition, string $field, int $sort): WorkflowTransitionValidationRule
    {
        return WorkflowTransitionValidationRule::create([
            'transition_id' => $transition->id,
            'field_name' => $field,
            'rules' => ['required'],
            'rule_type' => 'field',
            'sort_order' => $sort,
        ]);
    }

    public function test_the_rules_come_back_in_the_order_they_were_declared(): void
    {
        $transition = $this->transition();

        // Declared as zulu, alpha, mike — neither alphabetical nor the order they are written in here.
        $this->rule($transition, 'mike', 2);
        $this->rule($transition, 'zulu', 0);
        $this->rule($transition, 'alpha', 1);

        $this->assertSame(['zulu', 'alpha', 'mike'], $transition->validationRules()->pluck('field_name')->all());
        $this->assertSame(['zulu', 'alpha', 'mike'], $transition->fresh()->validationRules->pluck('field_name')->all());
    }

    public function test_two_rules_with_the_same_place_keep_the_order_they_were_written_in(): void
    {
        $transition = $this->transition();

        $this->rule($transition, 'zulu', 0);
        $this->rule($transition, 'mike', 0);
        $this->rule($transition, 'alpha', 0);

        $this->assertSame(['zulu', 'mike', 'alpha'], $transition->validationRules()->pluck('field_name')->all());
    }

    public function test_updating_a_rule_does_not_move_it(): void
    {
        $transition = $this->transition();

        $first = $this->rule($transition, 'zulu', 0);
        $this->rule($transition, 'alpha', 1);

        $first->update(['custom_message' => 'Changed']);

        $this->assertSame(['zulu', 'alpha'], $transition->validationRules()->pluck('field_name')->all());
    }
}
