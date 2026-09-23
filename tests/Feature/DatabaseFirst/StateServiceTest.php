<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Services\StateService;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * The states of a model as the service reads them: all of them in the declared order, an empty
 * stack when no workflow matches, and the metadata of one — label, colour, icon — with `null`
 * for a state that does not exist.
 */
class StateServiceTest extends TestCase
{
    private StateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StateService::class);
    }

    public function test_get_all_states_for_model_from_database(): void
    {
        $workflow = $this->createTestWorkflow();

        // Create database-only states (class_name = null) directly via model
        WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'pending',
            'label' => 'Pending',
            'color' => 'primary',
            'sort_order' => 0,
            'class_name' => null,
        ]);
        WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'processing',
            'label' => 'Processing',
            'color' => 'primary',
            'sort_order' => 1,
            'class_name' => null,
        ]);

        $states = $this->service->getAllStatesForModel(Order::class);

        $this->assertArrayHasKey('pending', $states);
        $this->assertArrayHasKey('processing', $states);
        $this->assertEquals('Pending', $states['pending']);
    }

    /**
     * The list of states is rendered as it comes: it has to come in the order the
     * workflow declares, not in the one a database happens to return.
     */
    public function test_get_all_states_follows_the_declared_order(): void
    {
        $workflow = $this->createTestWorkflow();

        // Created scrambled on purpose.
        foreach ([['shipped', 'Shipped', 3], ['pending', 'Pending', 1], ['processing', 'Processing', 2]] as [$name, $label, $sortOrder]) {
            WorkflowState::create([
                'workflow_id' => $workflow->id,
                'name' => $name,
                'label' => $label,
                'color' => 'primary',
                'sort_order' => $sortOrder,
                'class_name' => null,
            ]);
        }

        $this->assertSame(
            ['pending' => 'Pending', 'processing' => 'Processing', 'shipped' => 'Shipped'],
            $this->service->getAllStatesForModel(Order::class),
        );
    }

    public function test_get_all_states_returns_array_without_matching_workflow(): void
    {
        $states = $this->service->getAllStatesForModel(Order::class, 'nonexistent_column');
        $this->assertIsArray($states);
    }

    public function test_get_state_metadata(): void
    {
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'label' => 'Pending',
            'color' => 'warning',
            'icon' => 'heroicon-o-clock',
            'description' => 'Awaiting processing',
            'is_initial' => true,
            'is_final' => false,
        ]);

        $metadata = $this->service->getStateMetadata(Order::class, 'pending');

        $this->assertNotNull($metadata);
        $this->assertEquals('Pending', $metadata['label']);
        $this->assertEquals('warning', $metadata['color']);
        $this->assertEquals('heroicon-o-clock', $metadata['icon']);
        $this->assertEquals('Awaiting processing', $metadata['description']);
        $this->assertTrue($metadata['is_initial']);
        $this->assertFalse($metadata['is_final']);
    }

    public function test_get_state_metadata_returns_null_without_workflow(): void
    {
        $metadata = $this->service->getStateMetadata(Order::class, 'pending', 'nonexistent_column');
        $this->assertNull($metadata);
    }

    public function test_get_state_metadata_returns_null_for_unknown_state(): void
    {
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, ['name' => 'pending']);

        $metadata = $this->service->getStateMetadata(Order::class, 'nonexistent');
        $this->assertNull($metadata);
    }

    public function test_states_with_class_name_excluded_from_database_states(): void
    {
        $workflow = $this->createTestWorkflow();

        // State with a class_name (real PHP class) — excluded from getDatabaseStates
        $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'label' => 'Pending',
            'class_name' => PendingState::class,
        ]);

        // State without class_name (database-only) — included
        WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'refunded',
            'label' => 'Refunded',
            'color' => 'primary',
            'sort_order' => 1,
            'class_name' => null,
        ]);

        $states = $this->service->getAllStatesForModel(Order::class);

        $this->assertArrayHasKey('refunded', $states);
        $this->assertArrayNotHasKey('pending', $states);
    }

    public function test_get_all_states_respects_state_column(): void
    {
        $this->createTestWorkflow(['state_column' => 'status']);

        $states = $this->service->getAllStatesForModel(Order::class, 'state');
        $this->assertEmpty($states);
    }
}
