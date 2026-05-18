<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Commands;

use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class SyncStatesCommandTest extends TestCase
{
    public function test_sync_with_no_workflows(): void
    {
        $this->artisan('filament-flow:sync-states')
            ->expectsOutput('No workflows found.')
            ->assertExitCode(0);
    }

    public function test_sync_with_named_workflow_not_found(): void
    {
        $this->artisan('filament-flow:sync-states', ['--workflow' => 'NonExistent'])
            ->expectsOutputToContain('No workflow found with name "NonExistent"')
            ->assertExitCode(0);
    }

    public function test_sync_creates_states_from_spatie_classes(): void
    {
        // Order model uses OrderState which has concrete subclasses
        $workflow = $this->createTestWorkflow([
            'model_type' => Order::class,
            'state_column' => 'state',
        ]);

        $this->artisan('filament-flow:sync-states')
            ->assertExitCode(0);

        // Should have created states for PendingState, ProcessingState, etc.
        $stateCount = WorkflowState::where('workflow_id', $workflow->id)->count();
        $this->assertGreaterThan(0, $stateCount);
    }

    public function test_sync_updates_existing_states(): void
    {
        $workflow = $this->createTestWorkflow([
            'model_type' => Order::class,
            'state_column' => 'state',
        ]);

        // Run twice — second run should update, not duplicate
        $this->artisan('filament-flow:sync-states')->assertExitCode(0);
        $countAfterFirst = WorkflowState::where('workflow_id', $workflow->id)->count();

        $this->artisan('filament-flow:sync-states')->assertExitCode(0);
        $countAfterSecond = WorkflowState::where('workflow_id', $workflow->id)->count();

        $this->assertEquals($countAfterFirst, $countAfterSecond);
    }

    public function test_sync_skips_nonexistent_model(): void
    {
        $this->createTestWorkflow([
            'model_type' => 'App\\Models\\NonExistentModel',
        ]);

        $this->artisan('filament-flow:sync-states')
            ->expectsOutputToContain('does not exist, skipping')
            ->assertExitCode(0);
    }
}
