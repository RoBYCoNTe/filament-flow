<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionValidationRule;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class TransitionValidationRuleTest extends TestCase
{
    public function test_validation_rule_belongs_to_transition(): void
    {
        $workflow = $this->createTestWorkflow();
        $from = $this->createWorkflowState($workflow, ['name' => 's1']);
        $to = $this->createWorkflowState($workflow, ['name' => 's2']);
        $transition = $this->createWorkflowTransition($workflow, $from, $to);

        $rule = WorkflowTransitionValidationRule::create([
            'transition_id' => $transition->id,
            'field_name' => 'notes',
            'rules' => ['required', 'min:10'],
            'custom_message' => 'Notes must be at least 10 characters.',
            'sort_order' => 0,
        ]);

        $this->assertEquals($transition->id, $rule->transition->id);
    }

    public function test_rules_cast_to_array(): void
    {
        $workflow = $this->createTestWorkflow();
        $from = $this->createWorkflowState($workflow, ['name' => 's1']);
        $to = $this->createWorkflowState($workflow, ['name' => 's2']);
        $transition = $this->createWorkflowTransition($workflow, $from, $to);

        $rule = WorkflowTransitionValidationRule::create([
            'transition_id' => $transition->id,
            'field_name' => 'amount',
            'rules' => ['required', 'numeric', 'min:0'],
            'sort_order' => 0,
        ]);

        $rule->refresh();
        $this->assertIsArray($rule->rules);
        $this->assertContains('required', $rule->rules);
        $this->assertContains('numeric', $rule->rules);
    }

    public function test_transition_get_validation_rules(): void
    {
        $workflow = $this->createTestWorkflow();
        $from = $this->createWorkflowState($workflow, ['name' => 's1']);
        $to = $this->createWorkflowState($workflow, ['name' => 's2']);
        $transition = $this->createWorkflowTransition($workflow, $from, $to);

        WorkflowTransitionValidationRule::create([
            'transition_id' => $transition->id,
            'field_name' => 'notes',
            'rules' => ['required', 'string'],
            'sort_order' => 1,
        ]);

        WorkflowTransitionValidationRule::create([
            'transition_id' => $transition->id,
            'field_name' => 'amount',
            'rules' => ['required', 'numeric'],
            'sort_order' => 0,
        ]);

        $rules = $transition->getValidationRules();

        $this->assertArrayHasKey('amount', $rules);
        $this->assertArrayHasKey('notes', $rules);
        // sort_order respected: amount (0) before notes (1)
        $this->assertEquals(['amount', 'notes'], array_keys($rules));
    }

    public function test_transition_get_validation_messages(): void
    {
        $workflow = $this->createTestWorkflow();
        $from = $this->createWorkflowState($workflow, ['name' => 's1']);
        $to = $this->createWorkflowState($workflow, ['name' => 's2']);
        $transition = $this->createWorkflowTransition($workflow, $from, $to);

        WorkflowTransitionValidationRule::create([
            'transition_id' => $transition->id,
            'field_name' => 'notes',
            'rules' => ['required'],
            'custom_message' => 'Please provide notes.',
            'sort_order' => 0,
        ]);

        WorkflowTransitionValidationRule::create([
            'transition_id' => $transition->id,
            'field_name' => 'amount',
            'rules' => ['required'],
            'custom_message' => null,
            'sort_order' => 1,
        ]);

        $messages = $transition->getValidationMessages();

        $this->assertArrayHasKey('notes', $messages);
        $this->assertEquals('Please provide notes.', $messages['notes']);
        $this->assertArrayNotHasKey('amount', $messages);
    }

    public function test_transition_has_validation_rules(): void
    {
        $workflow = $this->createTestWorkflow();
        $from = $this->createWorkflowState($workflow, ['name' => 's1']);
        $to = $this->createWorkflowState($workflow, ['name' => 's2']);
        $transition = $this->createWorkflowTransition($workflow, $from, $to);

        $this->assertFalse($transition->hasValidationRules());

        WorkflowTransitionValidationRule::create([
            'transition_id' => $transition->id,
            'field_name' => 'notes',
            'rules' => ['required'],
            'sort_order' => 0,
        ]);

        $this->assertTrue($transition->hasValidationRules());
    }
}
