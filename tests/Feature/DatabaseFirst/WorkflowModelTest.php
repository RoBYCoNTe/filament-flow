<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowScheduledCheck;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class WorkflowModelTest extends TestCase
{
    public function test_find_for_model(): void
    {
        $workflow = $this->createTestWorkflow();

        $found = Workflow::findForModel(Order::class);
        $this->assertNotNull($found);
        $this->assertEquals($workflow->id, $found->id);
    }

    public function test_find_for_model_returns_null_for_unknown(): void
    {
        $found = Workflow::findForModel('App\\Models\\Unknown');
        $this->assertNull($found);
    }

    public function test_find_for_model_respects_state_column(): void
    {
        $this->createTestWorkflow(['state_column' => 'status']);

        $found = Workflow::findForModel(Order::class, 'status');
        $this->assertNotNull($found);

        $notFound = Workflow::findForModel(Order::class, 'state');
        $this->assertNull($notFound);
    }

    public function test_find_for_model_only_active(): void
    {
        $this->createTestWorkflow(['is_active' => false]);

        $found = Workflow::findForModel(Order::class);
        $this->assertNull($found);
    }

    public function test_initial_state(): void
    {
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, ['name' => 'active', 'is_initial' => false]);
        $initial = $this->createWorkflowState($workflow, ['name' => 'draft', 'is_initial' => true]);

        $result = $workflow->initialState();
        $this->assertNotNull($result);
        $this->assertEquals($initial->id, $result->id);
    }

    public function test_initial_state_returns_null_when_none(): void
    {
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, ['name' => 'active', 'is_initial' => false]);

        $this->assertNull($workflow->initialState());
    }

    public function test_final_states(): void
    {
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, ['name' => 'active', 'is_final' => false]);
        $this->createWorkflowState($workflow, ['name' => 'completed', 'is_final' => true]);
        $this->createWorkflowState($workflow, ['name' => 'cancelled', 'is_final' => true]);

        $finals = $workflow->finalStates()->get();
        $this->assertCount(2, $finals);
    }

    public function test_is_global(): void
    {
        $workflow = $this->createTestWorkflow();
        $this->assertTrue($workflow->isGlobal());
    }

    public function test_is_tenant_specific(): void
    {
        $workflow = $this->createTestWorkflow();
        $this->assertFalse($workflow->isTenantSpecific());
    }

    public function test_states_relationship(): void
    {
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, ['name' => 's1']);
        $this->createWorkflowState($workflow, ['name' => 's2']);

        $this->assertCount(2, $workflow->states);
    }

    public function test_transitions_relationship(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 's1']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 's2']);
        $this->createWorkflowTransition($workflow, $s1, $s2);

        $this->assertCount(1, $workflow->transitions);
    }

    public function test_scheduled_checks_relationship(): void
    {
        $workflow = $this->createTestWorkflow();

        WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'test',
            'condition_type' => 'field_compare',
            'condition_config' => ['conditions' => []],
            'action_type' => 'notification',
            'action_config' => [],
            'frequency' => 'daily',
            'is_active' => true,
        ]);

        $this->assertCount(1, $workflow->scheduledChecks);
    }

    public function test_creation_policy_cast(): void
    {
        $workflow = $this->createTestWorkflow([
            'creation_policy' => ['auto_assign_creator' => true, 'assignment_type' => 'primary'],
        ]);

        $workflow->refresh();
        $this->assertIsArray($workflow->creation_policy);
        $this->assertTrue($workflow->creation_policy['auto_assign_creator']);
    }

    public function test_scope_global(): void
    {
        $this->createTestWorkflow(['name' => 'Global WF']);

        $globals = Workflow::global()->get();
        $this->assertCount(1, $globals);
    }
}
