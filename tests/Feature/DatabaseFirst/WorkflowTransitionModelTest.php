<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionField;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionSideEffect;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class WorkflowTransitionModelTest extends TestCase
{
    public function test_is_action(): void
    {
        $workflow = $this->createTestWorkflow();

        $action = WorkflowTransition::create([
            'workflow_id' => $workflow->id,
            'from_state_id' => null,
            'to_state_id' => null,
            'name' => 'add_note',
            'label' => 'Add Note',
        ]);

        $this->assertTrue($action->isAction());
    }

    public function test_is_not_action(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 's1']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 's2']);
        $transition = $this->createWorkflowTransition($workflow, $s1, $s2);

        $this->assertFalse($transition->isAction());
    }

    public function test_is_global(): void
    {
        $workflow = $this->createTestWorkflow();
        $target = $this->createWorkflowState($workflow, ['name' => 'target']);

        $global = WorkflowTransition::create([
            'workflow_id' => $workflow->id,
            'from_state_id' => null,
            'to_state_id' => $target->id,
            'name' => 'global',
            'label' => 'Global',
        ]);

        $this->assertTrue($global->isGlobal());
    }

    public function test_is_not_global(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 's1']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 's2']);
        $transition = $this->createWorkflowTransition($workflow, $s1, $s2);

        $this->assertFalse($transition->isGlobal());
    }

    public function test_is_state_transition(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 's1']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 's2']);
        $transition = $this->createWorkflowTransition($workflow, $s1, $s2);

        $this->assertTrue($transition->isStateTransition());
    }

    public function test_is_not_state_transition_for_action(): void
    {
        $workflow = $this->createTestWorkflow();

        $action = WorkflowTransition::create([
            'workflow_id' => $workflow->id,
            'from_state_id' => null,
            'to_state_id' => null,
            'name' => 'action',
            'label' => 'Action',
        ]);

        $this->assertFalse($action->isStateTransition());
    }

    public function test_is_available_from_state(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 's1']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 's2']);
        $s3 = $this->createWorkflowState($workflow, ['name' => 's3']);
        $transition = $this->createWorkflowTransition($workflow, $s1, $s2);

        $this->assertTrue($transition->isAvailableFromState($s1->id));
        $this->assertFalse($transition->isAvailableFromState($s3->id));
    }

    public function test_global_transition_available_from_any_state(): void
    {
        $workflow = $this->createTestWorkflow();
        $target = $this->createWorkflowState($workflow, ['name' => 'target']);

        $global = WorkflowTransition::create([
            'workflow_id' => $workflow->id,
            'from_state_id' => null,
            'to_state_id' => $target->id,
            'name' => 'global',
            'label' => 'Global',
        ]);

        $this->assertTrue($global->isAvailableFromState(1));
        $this->assertTrue($global->isAvailableFromState(999));
        $this->assertTrue($global->isAvailableFromState(null));
    }

    public function test_has_valid_fields(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 's1']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 's2']);
        $transition = $this->createWorkflowTransition($workflow, $s1, $s2);

        $this->assertFalse($transition->hasValidFields());

        WorkflowTransitionField::create([
            'transition_id' => $transition->id,
            'field_name' => 'notes',
            'field_type' => 'textarea',
            'label' => 'Notes',
            'sort_order' => 0,
        ]);

        $transition->load('fields');
        $this->assertTrue($transition->hasValidFields());
    }

    public function test_active_side_effects_filter(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 's1']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 's2']);
        $transition = $this->createWorkflowTransition($workflow, $s1, $s2);

        WorkflowTransitionSideEffect::create([
            'transition_id' => $transition->id,
            'effect_type' => 'set_field',
            'field_name' => 'notes',
            'value_expression' => 'active',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        WorkflowTransitionSideEffect::create([
            'transition_id' => $transition->id,
            'effect_type' => 'set_field',
            'field_name' => 'carrier',
            'value_expression' => 'inactive',
            'sort_order' => 1,
            'is_active' => false,
        ]);

        $active = $transition->activeSideEffects()->get();
        $this->assertCount(1, $active);
        $this->assertEquals('notes', $active->first()->field_name);
    }

    public function test_conditions_cast_to_array(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 's1']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 's2']);

        $transition = $this->createWorkflowTransition($workflow, $s1, $s2, [
            'conditions' => [
                ['field' => 'total', 'operator' => '>=', 'value' => 100],
            ],
        ]);

        $transition->refresh();
        $this->assertIsArray($transition->conditions);
        $this->assertCount(1, $transition->conditions);
        $this->assertEquals('total', $transition->conditions[0]['field']);
    }

    public function test_relationships(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 's1']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 's2']);
        $transition = $this->createWorkflowTransition($workflow, $s1, $s2);

        $this->assertEquals($workflow->id, $transition->workflow->id);
        $this->assertEquals($s1->id, $transition->fromState->id);
        $this->assertEquals($s2->id, $transition->toState->id);
    }
}
