<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Test workflow configuration in database (Database-First approach)
 */
class WorkflowConfigurationTest extends TestCase
{
    /**
     * Test creating a workflow in database
     */
    public function test_create_workflow_in_database(): void
    {
        $workflow = Workflow::create([
            'name' => 'Order Workflow',
            'model_type' => Order::class,
            'state_column' => 'state',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('workflows', [
            'name' => 'Order Workflow',
            'model_type' => Order::class,
            'state_column' => 'state',
            'is_active' => true,
        ]);

        $this->assertNotNull($workflow->id);
        $this->assertEquals('Order Workflow', $workflow->name);
    }

    /**
     * Test creating workflow states
     */
    public function test_create_workflow_states(): void
    {
        $workflow = $this->createTestWorkflow([
            'name' => 'Test Workflow',
        ]);

        WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'pending',
            'label' => 'Pending',
            'color' => 'warning',
            'sort_order' => 10,
            'is_initial' => true,
            'is_final' => false,
        ]);

        WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'processing',
            'label' => 'Processing',
            'color' => 'info',
            'sort_order' => 20,
            'is_initial' => false,
            'is_final' => false,
        ]);

        $this->assertDatabaseHas('workflow_states', [
            'workflow_id' => $workflow->id,
            'name' => 'pending',
            'is_initial' => true,
        ]);

        $this->assertDatabaseHas('workflow_states', [
            'workflow_id' => $workflow->id,
            'name' => 'processing',
            'is_initial' => false,
        ]);

        $this->assertCount(2, $workflow->states);
    }

    /**
     * Test creating workflow transitions
     */
    public function test_create_workflow_transitions(): void
    {
        $workflow = $this->createTestWorkflow();

        $pendingState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'label' => 'Pending',
        ]);

        $processingState = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'label' => 'Processing',
        ]);

        $transition = WorkflowTransition::create([
            'workflow_id' => $workflow->id,
            'from_state_id' => $pendingState->id,
            'to_state_id' => $processingState->id,
            'name' => 'start_processing',
            'label' => 'Start Processing',
            'requires_confirmation' => false,
        ]);

        $this->assertDatabaseHas('workflow_transitions', [
            'workflow_id' => $workflow->id,
            'from_state_id' => $pendingState->id,
            'to_state_id' => $processingState->id,
            'name' => 'start_processing',
        ]);

        $this->assertEquals('Start Processing', $transition->label);
    }

    /**
     * Test workflow links to model
     */
    public function test_workflow_links_to_model(): void
    {
        $workflow = Workflow::create([
            'name' => 'Order Workflow',
            'model_type' => Order::class,
            'state_column' => 'state',
            'is_active' => true,
        ]);

        $this->assertEquals(Order::class, $workflow->model_type);
        $this->assertEquals('state', $workflow->state_column);

        // Verify workflow can be retrieved by model type
        $found = Workflow::where('model_type', Order::class)->first();
        $this->assertNotNull($found);
        $this->assertEquals($workflow->id, $found->id);
    }

    /**
     * Test workflow can be deactivated
     */
    public function test_workflow_activation_toggle(): void
    {
        $workflow = Workflow::create([
            'name' => 'Test Workflow',
            'model_type' => Order::class,
            'state_column' => 'state',
            'is_active' => true,
        ]);

        $this->assertTrue($workflow->is_active);

        $workflow->update(['is_active' => false]);
        $workflow->refresh();

        $this->assertFalse($workflow->is_active);
    }

    /**
     * Test workflow states are ordered
     */
    public function test_workflow_states_ordered(): void
    {
        $workflow = $this->createTestWorkflow();

        WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'state1',
            'label' => 'State 1',
            'sort_order' => 30,
        ]);

        WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'state2',
            'label' => 'State 2',
            'sort_order' => 10,
        ]);

        WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'state3',
            'label' => 'State 3',
            'sort_order' => 20,
        ]);

        $orderedStates = $workflow->states()->orderBy('sort_order')->get();

        $this->assertEquals('state2', $orderedStates[0]->name);
        $this->assertEquals('state3', $orderedStates[1]->name);
        $this->assertEquals('state1', $orderedStates[2]->name);
    }

    /**
     * Test workflow initial state
     *
     * @noinspection PhpExpressionAlwaysNullInspection
     */
    public function test_workflow_has_initial_state(): void
    {
        $workflow = $this->createTestWorkflow();

        WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'initial',
            'label' => 'Initial',
            'is_initial' => true,
        ]);

        WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'normal',
            'label' => 'Normal',
            'is_initial' => false,
        ]);

        $foundInitial = $workflow->states()->where('is_initial', true)->first();

        $this->assertNotNull($foundInitial);
        $this->assertEquals('initial', $foundInitial->name);
    }

    /**
     * Test workflow final state
     *
     * @noinspection PhpExpressionAlwaysNullInspection
     */
    public function test_workflow_has_final_state(): void
    {
        $workflow = $this->createTestWorkflow();

        WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'normal',
            'label' => 'Normal',
            'is_final' => false,
        ]);

        WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'final',
            'label' => 'Final',
            'is_final' => true,
        ]);

        $foundFinal = $workflow->states()->where('is_final', true)->first();

        $this->assertNotNull($foundFinal);
        $this->assertEquals('final', $foundFinal->name);
    }
}
