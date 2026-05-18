<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use Carbon\Carbon;
use RoBYCoNTe\FilamentFlow\Models\WorkflowScheduledCheck;
use RoBYCoNTe\FilamentFlow\Models\WorkflowScheduledCheckLog;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class WorkflowScheduledCheckModelTest extends TestCase
{
    public function test_is_due_when_never_checked(): void
    {
        $workflow = $this->createTestWorkflow();

        $check = WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'test_check',
            'condition_type' => 'field_compare',
            'condition_config' => ['conditions' => []],
            'action_type' => 'notification',
            'action_config' => [],
            'frequency' => 'daily',
            'is_active' => true,
            'last_checked_at' => null,
        ]);

        $this->assertTrue($check->isDue());
    }

    public function test_is_due_daily_not_yet(): void
    {
        $workflow = $this->createTestWorkflow();

        $check = WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'test_check',
            'condition_type' => 'field_compare',
            'condition_config' => ['conditions' => []],
            'action_type' => 'notification',
            'action_config' => [],
            'frequency' => 'daily',
            'is_active' => true,
            'last_checked_at' => Carbon::now()->subHours(12),
        ]);

        $this->assertFalse($check->isDue());
    }

    public function test_is_due_daily_past(): void
    {
        $workflow = $this->createTestWorkflow();

        $check = WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'test_check',
            'condition_type' => 'field_compare',
            'condition_config' => ['conditions' => []],
            'action_type' => 'notification',
            'action_config' => [],
            'frequency' => 'daily',
            'is_active' => true,
            'last_checked_at' => Carbon::now()->subDays(2),
        ]);

        $this->assertTrue($check->isDue());
    }

    public function test_is_due_hourly(): void
    {
        $workflow = $this->createTestWorkflow();

        $check = WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'test_check',
            'condition_type' => 'field_compare',
            'condition_config' => ['conditions' => []],
            'action_type' => 'notification',
            'action_config' => [],
            'frequency' => 'hourly',
            'is_active' => true,
            'last_checked_at' => Carbon::now()->subHours(2),
        ]);

        $this->assertTrue($check->isDue());
    }

    public function test_is_due_every_minute(): void
    {
        $workflow = $this->createTestWorkflow();

        $check = WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'test_check',
            'condition_type' => 'field_compare',
            'condition_config' => ['conditions' => []],
            'action_type' => 'notification',
            'action_config' => [],
            'frequency' => 'every_minute',
            'is_active' => true,
            'last_checked_at' => Carbon::now()->subMinutes(2),
        ]);

        $this->assertTrue($check->isDue());
    }

    public function test_is_due_weekly(): void
    {
        $workflow = $this->createTestWorkflow();

        $check = WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'test_check',
            'condition_type' => 'field_compare',
            'condition_config' => ['conditions' => []],
            'action_type' => 'notification',
            'action_config' => [],
            'frequency' => 'weekly',
            'is_active' => true,
            'last_checked_at' => Carbon::now()->subDays(3),
        ]);

        $this->assertFalse($check->isDue());
    }

    public function test_is_due_every_five_minutes(): void
    {
        $workflow = $this->createTestWorkflow();

        $check = WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'test_check',
            'condition_type' => 'field_compare',
            'condition_config' => ['conditions' => []],
            'action_type' => 'notification',
            'action_config' => [],
            'frequency' => 'every_five_minutes',
            'is_active' => true,
            'last_checked_at' => Carbon::now()->subMinutes(10),
        ]);

        $this->assertTrue($check->isDue());
    }

    public function test_has_already_executed_for_with_once_per_record(): void
    {
        $workflow = $this->createTestWorkflow();

        $check = WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'test_once',
            'condition_type' => 'field_compare',
            'condition_config' => ['conditions' => []],
            'action_type' => 'notification',
            'action_config' => [],
            'frequency' => 'daily',
            'once_per_record' => true,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-SC-001',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'pending',
        ]);

        $this->assertFalse($check->hasAlreadyExecutedFor(Order::class, $order->id));

        // Log a triggered execution
        WorkflowScheduledCheckLog::create([
            'check_id' => $check->id,
            'model_type' => Order::class,
            'model_id' => $order->id,
            'result' => 'triggered',
        ]);

        $this->assertTrue($check->hasAlreadyExecutedFor(Order::class, $order->id));
    }

    public function test_has_already_executed_for_without_once_per_record(): void
    {
        $workflow = $this->createTestWorkflow();

        $check = WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'test_repeat',
            'condition_type' => 'field_compare',
            'condition_config' => ['conditions' => []],
            'action_type' => 'notification',
            'action_config' => [],
            'frequency' => 'daily',
            'once_per_record' => false,
            'is_active' => true,
        ]);

        // Even with a log, once_per_record=false means it always returns false
        $this->assertFalse($check->hasAlreadyExecutedFor(Order::class, 999));
    }

    public function test_relationships(): void
    {
        $workflow = $this->createTestWorkflow();

        $check = WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'test_rel',
            'condition_type' => 'field_compare',
            'condition_config' => ['conditions' => []],
            'action_type' => 'notification',
            'action_config' => [],
            'frequency' => 'daily',
            'is_active' => true,
        ]);

        $this->assertEquals($workflow->id, $check->workflow->id);
    }

    public function test_condition_config_cast_to_array(): void
    {
        $workflow = $this->createTestWorkflow();

        $check = WorkflowScheduledCheck::create([
            'workflow_id' => $workflow->id,
            'name' => 'test_cast',
            'condition_type' => 'field_compare',
            'condition_config' => ['conditions' => [['field' => 'total', 'operator' => '>', 'value' => 100]]],
            'action_type' => 'notification',
            'action_config' => ['template' => 'overdue'],
            'frequency' => 'daily',
            'is_active' => true,
        ]);

        $check->refresh();
        $this->assertIsArray($check->condition_config);
        $this->assertIsArray($check->action_config);
        $this->assertEquals('total', $check->condition_config['conditions'][0]['field']);
    }
}
