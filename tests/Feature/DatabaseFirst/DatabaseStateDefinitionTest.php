<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Test database-defined state metadata (Database-First approach)
 */
class DatabaseStateDefinitionTest extends TestCase
{
    /**
     * Test creating a state with metadata
     */
    public function test_create_state_with_metadata(): void
    {
        $workflow = $this->createTestWorkflow();

        $state = WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'pending',
            'label' => 'Pending Order',
            'description' => 'Order is waiting for processing',
            'color' => 'warning',
            'sort_order' => 10,
            'is_initial' => true,
            'is_final' => false,
            'class_name' => 'pending',
        ]);

        $this->assertEquals('pending', $state->name);
        $this->assertEquals('Pending Order', $state->label);
        $this->assertEquals('Order is waiting for processing', $state->description);
        $this->assertEquals('warning', $state->color);
        $this->assertTrue($state->is_initial);
        $this->assertFalse($state->is_final);
    }

    /**
     * Test state label is retrievable
     */
    public function test_state_label_retrievable(): void
    {
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'label' => 'Processing',
            'description' => 'Order is being processed',
        ]);

        $this->assertEquals('Processing', $state->label);
    }

    /**
     * Test state description is retrievable
     */
    public function test_state_description_retrievable(): void
    {
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, [
            'name' => 'shipped',
            'description' => 'Order has been shipped to customer',
        ]);

        $this->assertEquals('Order has been shipped to customer', $state->description);
    }

    /**
     * Test state color is retrievable
     */
    public function test_state_color_retrievable(): void
    {
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, [
            'name' => 'delivered',
            'color' => 'success',
        ]);

        $this->assertEquals('success', $state->color);
    }

    /**
     * Test multiple states with different metadata
     */
    public function test_multiple_states_with_different_metadata(): void
    {
        $workflow = $this->createTestWorkflow();

        $pending = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'label' => 'Pending',
            'color' => 'warning',
        ]);

        $processing = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'label' => 'Processing',
            'color' => 'info',
        ]);

        $completed = $this->createWorkflowState($workflow, [
            'name' => 'completed',
            'label' => 'Completed',
            'color' => 'success',
        ]);

        $this->assertEquals('warning', $pending->color);
        $this->assertEquals('info', $processing->color);
        $this->assertEquals('success', $completed->color);
    }

    /**
     * Test state initial and final flags
     */
    public function test_state_initial_and_final_flags(): void
    {
        $workflow = $this->createTestWorkflow();

        $initial = $this->createWorkflowState($workflow, [
            'name' => 'draft',
            'is_initial' => true,
            'is_final' => false,
        ]);

        $final = $this->createWorkflowState($workflow, [
            'name' => 'archived',
            'is_initial' => false,
            'is_final' => true,
        ]);

        $this->assertTrue($initial->is_initial);
        $this->assertFalse($initial->is_final);

        $this->assertFalse($final->is_initial);
        $this->assertTrue($final->is_final);
    }

    /**
     * Test retrieving states from workflow
     */
    public function test_retrieve_states_from_workflow(): void
    {
        $workflow = $this->createTestWorkflow();

        $state1 = $this->createWorkflowState($workflow, ['name' => 'state1']);
        $state2 = $this->createWorkflowState($workflow, ['name' => 'state2']);
        $state3 = $this->createWorkflowState($workflow, ['name' => 'state3']);

        $states = $workflow->states;

        $this->assertCount(3, $states);
        $this->assertTrue($states->contains($state1));
        $this->assertTrue($states->contains($state2));
        $this->assertTrue($states->contains($state3));
    }

    /**
     * Test state sort order
     */
    public function test_state_sort_order(): void
    {
        $workflow = $this->createTestWorkflow();

        $this->createWorkflowState($workflow, [
            'name' => 'state1',
            'sort_order' => 30,
        ]);

        $this->createWorkflowState($workflow, [
            'name' => 'state2',
            'sort_order' => 10,
        ]);

        $this->createWorkflowState($workflow, [
            'name' => 'state3',
            'sort_order' => 20,
        ]);

        $ordered = $workflow->states()->orderBy('sort_order')->get();

        $this->assertEquals('state2', $ordered[0]->name);
        $this->assertEquals('state3', $ordered[1]->name);
        $this->assertEquals('state1', $ordered[2]->name);
    }
}
