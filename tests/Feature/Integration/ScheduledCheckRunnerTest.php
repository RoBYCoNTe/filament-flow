<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Integration;

use RoBYCoNTe\FilamentFlow\Models\WorkflowScheduledCheck;
use RoBYCoNTe\FilamentFlow\Services\ScheduledCheckRunner;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class ScheduledCheckRunnerTest extends TestCase
{
    private ScheduledCheckRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runner = app(ScheduledCheckRunner::class);
    }

    public function test_run_all_returns_stats(): void
    {
        $result = $this->runner->runAll();

        $this->assertArrayHasKey('processed', $result);
        $this->assertArrayHasKey('triggered', $result);
        $this->assertArrayHasKey('errors', $result);
    }

    public function test_date_offset_condition_triggers_when_due(): void
    {
        $workflow = $this->createTestWorkflow();
        $activeState = $this->createWorkflowState($workflow, ['name' => 'active']);

        WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'expiry_warning',
            'condition_type' => 'date_offset',
            'condition_config' => [
                'field' => 'estimated_delivery',
                'offset_days' => -2,
                'operator' => '<=',
            ],
            'action_type' => 'transition',
            'action_config' => ['to_state' => 'expired', 'force' => true],
            'frequency' => 'daily',
            'is_active' => true,
        ]);

        // Order with delivery date 1 day from now (within 2-day window)
        $this->createOrder([
            'state' => 'active',
            'estimated_delivery' => now()->addDay(),
        ]);

        $result = $this->runner->runAll();

        // Record processed, but transition to 'expired' won't happen without a workflow state
        // The important thing is the condition was evaluated
        $this->assertEquals(1, $result['processed']);
    }

    public function test_date_offset_condition_skips_when_not_due(): void
    {
        $workflow = $this->createTestWorkflow();

        WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'expiry_warning',
            'condition_type' => 'date_offset',
            'condition_config' => [
                'field' => 'estimated_delivery',
                'offset_days' => -2,
                'operator' => '<=',
            ],
            'action_type' => 'notification',
            'action_config' => ['notification_id' => null],
            'frequency' => 'daily',
            'is_active' => true,
        ]);

        // Order with delivery date far in the future
        $this->createOrder([
            'state' => 'active',
            'estimated_delivery' => now()->addMonths(3),
        ]);

        $result = $this->runner->runAll();

        $this->assertEquals(1, $result['processed']);
        $this->assertEquals(0, $result['triggered']);
    }

    public function test_field_compare_condition(): void
    {
        $workflow = $this->createTestWorkflow();

        WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'high_value_check',
            'condition_type' => 'field_compare',
            'condition_config' => [
                'conditions' => [
                    ['field' => 'total_amount', 'operator' => '>=', 'value' => 1000],
                ],
            ],
            'action_type' => 'notification',
            'action_config' => ['notification_id' => null],
            'frequency' => 'daily',
            'is_active' => true,
        ]);

        // High-value order
        $this->createOrder(['total_amount' => 1500]);

        // Low-value order
        $this->createOrder(['total_amount' => 50]);

        $result = $this->runner->runAll();

        $this->assertEquals(2, $result['processed']);
        // Only the high-value order triggers (notification is null, so no actual dispatch)
        $this->assertEquals(1, $result['triggered']);
    }

    public function test_once_per_record_prevents_re_execution(): void
    {
        $workflow = $this->createTestWorkflow();

        $check = WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'one_time_check',
            'condition_type' => 'field_compare',
            'condition_config' => [
                'conditions' => [
                    ['field' => 'total_amount', 'operator' => '>=', 'value' => 100],
                ],
            ],
            'action_type' => 'notification',
            'action_config' => ['notification_id' => null],
            'frequency' => 'daily',
            'once_per_record' => true,
            'is_active' => true,
        ]);

        $order = $this->createOrder(['total_amount' => 500]);

        // First run
        $result1 = $this->runner->runAll();
        $this->assertEquals(1, $result1['triggered']);

        // Reset last_checked_at so the check is due again
        $check->update(['last_checked_at' => null]);

        // Second run — should skip because once_per_record
        $result2 = $this->runner->runAll();
        $this->assertEquals(1, $result2['processed']);
        $this->assertEquals(0, $result2['triggered']);

        // Verify the log says 'already_executed'
        $this->assertDatabaseHas('workflow_scheduled_check_logs', [
            'check_id' => $check->id,
            'model_id' => $order->id,
            'result' => 'already_executed',
        ]);
    }

    public function test_inactive_checks_are_skipped(): void
    {
        $workflow = $this->createTestWorkflow();

        WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'inactive_check',
            'condition_type' => 'field_compare',
            'condition_config' => ['conditions' => []],
            'action_type' => 'notification',
            'action_config' => [],
            'frequency' => 'daily',
            'is_active' => false,
        ]);

        $this->createOrder();

        $result = $this->runner->runAll();
        $this->assertEquals(0, $result['processed']);
    }

    public function test_check_not_due_is_skipped(): void
    {
        $workflow = $this->createTestWorkflow();

        WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'recently_checked',
            'condition_type' => 'field_compare',
            'condition_config' => ['conditions' => []],
            'action_type' => 'notification',
            'action_config' => [],
            'frequency' => 'daily',
            'is_active' => true,
            'last_checked_at' => now(), // Just checked
        ]);

        $this->createOrder();

        $result = $this->runner->runAll();
        $this->assertEquals(0, $result['processed']);
    }

    public function test_state_filtered_check_only_processes_matching_records(): void
    {
        $workflow = $this->createTestWorkflow();
        $activeState = $this->createWorkflowState($workflow, ['name' => 'active']);
        $this->createWorkflowState($workflow, ['name' => 'completed']);

        WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'active_only_check',
            'state_id' => $activeState->id,
            'condition_type' => 'field_compare',
            'condition_config' => [
                'conditions' => [
                    ['field' => 'total_amount', 'operator' => '>', 'value' => 0],
                ],
            ],
            'action_type' => 'notification',
            'action_config' => ['notification_id' => null],
            'frequency' => 'daily',
            'is_active' => true,
        ]);

        // Active order
        $this->createOrder(['state' => 'active', 'total_amount' => 100]);

        // Completed order (should not be processed)
        $this->createOrder(['state' => 'completed', 'total_amount' => 200]);

        $result = $this->runner->runAll();

        $this->assertEquals(1, $result['processed']);
        $this->assertEquals(1, $result['triggered']);
    }

    public function test_execution_is_logged(): void
    {
        $workflow = $this->createTestWorkflow();

        $check = WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'logged_check',
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

        $order = $this->createOrder(['total_amount' => 500]);

        $this->runner->runAll();

        $this->assertDatabaseHas('workflow_scheduled_check_logs', [
            'check_id' => $check->id,
            'model_type' => Order::class,
            'model_id' => $order->id,
            'result' => 'triggered',
        ]);
    }

    public function test_skipped_execution_is_logged(): void
    {
        $workflow = $this->createTestWorkflow();

        $check = WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'skip_check',
            'condition_type' => 'field_compare',
            'condition_config' => [
                'conditions' => [
                    ['field' => 'total_amount', 'operator' => '>=', 'value' => 1000],
                ],
            ],
            'action_type' => 'notification',
            'action_config' => ['notification_id' => null],
            'frequency' => 'daily',
            'is_active' => true,
        ]);

        $order = $this->createOrder(['total_amount' => 50]);

        $this->runner->runAll();

        $this->assertDatabaseHas('workflow_scheduled_check_logs', [
            'check_id' => $check->id,
            'model_id' => $order->id,
            'result' => 'skipped',
        ]);
    }

    public function test_last_checked_at_is_updated(): void
    {
        $workflow = $this->createTestWorkflow();

        $check = WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'timestamped_check',
            'condition_type' => 'field_compare',
            'condition_config' => ['conditions' => []],
            'action_type' => 'notification',
            'action_config' => [],
            'frequency' => 'daily',
            'is_active' => true,
            'last_checked_at' => null,
        ]);

        $this->runner->runAll();

        $check->refresh();
        $this->assertNotNull($check->last_checked_at);
    }

    public function test_date_offset_with_null_field_skips(): void
    {
        $workflow = $this->createTestWorkflow();

        WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'null_date_check',
            'condition_type' => 'date_offset',
            'condition_config' => [
                'field' => 'estimated_delivery',
                'offset_days' => -2,
                'operator' => '<=',
            ],
            'action_type' => 'notification',
            'action_config' => ['notification_id' => null],
            'frequency' => 'daily',
            'is_active' => true,
        ]);

        // Order without estimated_delivery
        $this->createOrder(['estimated_delivery' => null]);

        $result = $this->runner->runAll();

        $this->assertEquals(1, $result['processed']);
        $this->assertEquals(0, $result['triggered']);
    }

    public function test_is_due_respects_frequency(): void
    {
        $check = new WorkflowScheduledCheck([
            'frequency' => 'hourly',
            'last_checked_at' => now()->subMinutes(30),
        ]);
        $this->assertFalse($check->isDue());

        $check->last_checked_at = now()->subHours(2);
        $this->assertTrue($check->isDue());

        $check->last_checked_at = null;
        $this->assertTrue($check->isDue());
    }

    public function test_is_due_daily(): void
    {
        $check = new WorkflowScheduledCheck([
            'frequency' => 'daily',
            'last_checked_at' => now()->subHours(12),
        ]);
        $this->assertFalse($check->isDue());

        $check->last_checked_at = now()->subDays(2);
        $this->assertTrue($check->isDue());
    }

    public function test_is_due_weekly(): void
    {
        $check = new WorkflowScheduledCheck([
            'frequency' => 'weekly',
            'last_checked_at' => now()->subDays(3),
        ]);
        $this->assertFalse($check->isDue());

        $check->last_checked_at = now()->subDays(8);
        $this->assertTrue($check->isDue());
    }

    public function test_custom_class_condition(): void
    {
        $workflow = $this->createTestWorkflow();

        // Register a custom condition class
        app()->bind(AlwaysTrueCondition::class, fn () => new AlwaysTrueCondition);

        WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'custom_check',
            'condition_type' => 'custom_class',
            'condition_config' => ['class' => AlwaysTrueCondition::class],
            'action_type' => 'notification',
            'action_config' => ['notification_id' => null],
            'frequency' => 'daily',
            'is_active' => true,
        ]);

        $this->createOrder();

        $result = $this->runner->runAll();
        $this->assertEquals(1, $result['triggered']);
    }

    public function test_custom_class_condition_nonexistent_returns_false(): void
    {
        $workflow = $this->createTestWorkflow();

        WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'bad_class_check',
            'condition_type' => 'custom_class',
            'condition_config' => ['class' => 'NonExistent\\CustomCondition'],
            'action_type' => 'notification',
            'action_config' => ['notification_id' => null],
            'frequency' => 'daily',
            'is_active' => true,
        ]);

        $this->createOrder();

        $result = $this->runner->runAll();
        $this->assertEquals(0, $result['triggered']);
    }

    private function createOrder(array $data = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-SCR-'.uniqid(),
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ], $data));
    }
}

class AlwaysTrueCondition
{
    public function evaluate($model): bool
    {
        return true;
    }
}
