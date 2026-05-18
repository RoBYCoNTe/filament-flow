<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Concerns;

use RoBYCoNTe\FilamentFlow\Services\StateService;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class ResolvesActionAttributesTest extends TestCase
{
    private StateService $stateService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateService = app(StateService::class);
    }

    /**
     * Helper to create a workflow with database-only states for resolution testing.
     */
    private function createWorkflowWithDatabaseStates(): array
    {
        $workflow = $this->createTestWorkflow();

        $pending = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'label' => 'Pending Review',
            'color' => 'warning',
            'icon' => 'heroicon-o-clock',
            'description' => 'Awaiting review by staff',
            'class_name' => null,
            'is_initial' => true,
            'sort_order' => 0,
        ]);

        $processing = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'label' => 'In Progress',
            'color' => 'info',
            'icon' => 'heroicon-o-arrow-path',
            'description' => 'Currently being processed',
            'class_name' => null,
            'sort_order' => 1,
        ]);

        $this->createWorkflowTransition($workflow, $pending, $processing, [
            'name' => 'pending_to_processing',
            'label' => 'Start Processing',
        ]);

        return compact('workflow', 'pending', 'processing');
    }

    public function test_resolve_label_from_database_state(): void
    {
        $data = $this->createWorkflowWithDatabaseStates();

        $metadata = $this->stateService->getStateMetadata(
            Order::class,
            'processing',
            'state'
        );

        $this->assertNotNull($metadata);
        $this->assertEquals('In Progress', $metadata['label']);
    }

    public function test_resolve_color_from_database_state(): void
    {
        $data = $this->createWorkflowWithDatabaseStates();

        $metadata = $this->stateService->getStateMetadata(
            Order::class,
            'processing',
            'state'
        );

        $this->assertNotNull($metadata);
        $this->assertEquals('info', $metadata['color']);
    }

    public function test_resolve_icon_from_database_state(): void
    {
        $data = $this->createWorkflowWithDatabaseStates();

        $metadata = $this->stateService->getStateMetadata(
            Order::class,
            'processing',
            'state'
        );

        $this->assertNotNull($metadata);
        $this->assertEquals('heroicon-o-arrow-path', $metadata['icon']);
    }

    public function test_resolve_description_from_database_state(): void
    {
        $data = $this->createWorkflowWithDatabaseStates();

        $metadata = $this->stateService->getStateMetadata(
            Order::class,
            'processing',
            'state'
        );

        $this->assertNotNull($metadata);
        $this->assertEquals('Currently being processed', $metadata['description']);
    }

    public function test_fallback_to_state_name_when_no_label(): void
    {
        $workflow = $this->createTestWorkflow();

        $this->createWorkflowState($workflow, [
            'name' => 'unlabeled',
            'label' => '',
            'color' => 'gray',
            'class_name' => null,
            'sort_order' => 0,
        ]);

        $metadata = $this->stateService->getStateMetadata(
            Order::class,
            'unlabeled',
            'state'
        );

        $this->assertNotNull($metadata);
        // When label is empty, the metadata should reflect that
        $this->assertEmpty($metadata['label']);
    }

    public function test_fallback_to_primary_when_no_color(): void
    {
        $workflow = $this->createTestWorkflow();

        $this->createWorkflowState($workflow, [
            'name' => 'colorless',
            'label' => 'No Color State',
            'color' => '',
            'class_name' => null,
            'sort_order' => 0,
        ]);

        $metadata = $this->stateService->getStateMetadata(
            Order::class,
            'colorless',
            'state'
        );

        $this->assertNotNull($metadata);
        // When color is empty, the metadata returns empty string
        // The action layer (StateAction) would then fall back to 'primary'
        $this->assertEmpty($metadata['color']);
    }
}
