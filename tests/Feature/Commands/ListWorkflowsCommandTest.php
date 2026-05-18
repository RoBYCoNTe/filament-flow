<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Commands;

use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class ListWorkflowsCommandTest extends TestCase
{
    public function test_list_workflows_when_empty(): void
    {
        $this->artisan('filament-flow:list')
            ->expectsOutput('No workflows found.')
            ->assertExitCode(0);
    }

    public function test_list_workflows_shows_table(): void
    {
        $workflow = $this->createTestWorkflow(['name' => 'Order Workflow']);
        $this->createWorkflowState($workflow, ['name' => 'pending']);
        $this->createWorkflowState($workflow, ['name' => 'done']);

        $this->artisan('filament-flow:list')
            ->assertExitCode(0);
    }

    public function test_list_workflows_shows_multiple(): void
    {
        $this->createTestWorkflow(['name' => 'Workflow A', 'model_type' => 'App\\Models\\ModelA']);
        $this->createTestWorkflow(['name' => 'Workflow B', 'model_type' => 'App\\Models\\ModelB']);

        $this->artisan('filament-flow:list')
            ->assertExitCode(0);
    }
}
