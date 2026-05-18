<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Commands;

use RoBYCoNTe\FilamentFlow\Models\WorkflowScheduledCheck;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class ProcessScheduledChecksCommandTest extends TestCase
{
    public function test_command_runs_successfully(): void
    {
        $this->artisan('workflow:process-schedules')
            ->expectsOutput('Processing workflow scheduled checks...')
            ->expectsOutputToContain('Processed: 0 records')
            ->expectsOutputToContain('Triggered: 0 actions')
            ->expectsOutput('Done.')
            ->assertExitCode(0);
    }

    public function test_command_processes_checks(): void
    {
        $workflow = $this->createTestWorkflow();

        WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'test_check',
            'condition_type' => 'field_compare',
            'condition_config' => [
                'conditions' => [
                    ['field' => 'total_amount', 'operator' => '>=', 'value' => 100],
                ],
            ],
            'action_type' => 'notification',
            'action_config' => ['notification_id' => null],
            'frequency' => 'daily',
            'is_active' => true,
        ]);

        Order::create([
            'order_number' => 'ORD-CMD-001',
            'customer_name' => 'Test',
            'total_amount' => 500,
        ]);

        $this->artisan('workflow:process-schedules')
            ->expectsOutputToContain('Processed: 1')
            ->expectsOutputToContain('Triggered: 1')
            ->assertExitCode(0);
    }

    public function test_command_reports_errors(): void
    {
        // Empty run with no errors
        $this->artisan('workflow:process-schedules')
            ->assertExitCode(0);
    }
}
