<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionPermission;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class TransitionPermissionsTest extends TestCase
{
    public function test_transition_without_permissions_is_allowed(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'processing']);
        $this->createWorkflowTransition($workflow, $s1, $s2);

        $order = $this->createOrder(['state' => 'pending']);
        $this->assertTrue($order->canTransitionTo('processing'));
    }

    public function test_role_permission_allows_matching_user(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'processing']);
        $transition = $this->createWorkflowTransition($workflow, $s1, $s2);

        WorkflowTransitionPermission::create([
            'transition_id' => $transition->id,
            'permission_type' => 'role',
            'permission_value' => 'admin',
        ]);

        $admin = $this->createTestUser(['role' => 'admin']);
        $editor = $this->createTestUser(['email' => 'editor@test.com', 'role' => 'editor']);

        $order = $this->createOrder(['state' => 'pending']);

        $this->assertTrue($order->asUser($admin)->canTransitionTo('processing'));
        $this->assertFalse($order->asUser($editor)->canTransitionTo('processing'));
    }

    public function test_comma_separated_roles(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'processing']);
        $transition = $this->createWorkflowTransition($workflow, $s1, $s2);

        WorkflowTransitionPermission::create([
            'transition_id' => $transition->id,
            'permission_type' => 'role',
            'permission_value' => 'admin,manager',
        ]);

        $admin = $this->createTestUser(['role' => 'admin']);
        $manager = $this->createTestUser(['email' => 'mgr@test.com', 'role' => 'manager']);
        $editor = $this->createTestUser(['email' => 'ed@test.com', 'role' => 'editor']);

        $order = $this->createOrder(['state' => 'pending']);

        $this->assertTrue($order->asUser($admin)->canTransitionTo('processing'));
        $this->assertTrue($order->asUser($manager)->canTransitionTo('processing'));
        $this->assertFalse($order->asUser($editor)->canTransitionTo('processing'));
    }

    public function test_assignment_permission(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'in_progress']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'review']);
        $transition = $this->createWorkflowTransition($workflow, $s1, $s2);

        WorkflowTransitionPermission::create([
            'transition_id' => $transition->id,
            'permission_type' => 'assignment',
        ]);

        $assignedUser = $this->createTestUser(['email' => 'assigned@test.com']);
        $otherUser = $this->createTestUser(['email' => 'other@test.com']);

        $order = $this->createOrder(['state' => 'in_progress']);

        // Assign user to order
        $order->assignTo($assignedUser, 'primary', $assignedUser);

        $this->assertTrue($order->asUser($assignedUser)->canTransitionTo('review'));
        $this->assertFalse($order->asUser($otherUser)->canTransitionTo('review'));
    }

    public function test_or_logic_any_permission_passes(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'done']);
        $transition = $this->createWorkflowTransition($workflow, $s1, $s2);

        // Role OR assignment
        WorkflowTransitionPermission::create([
            'transition_id' => $transition->id,
            'permission_type' => 'role',
            'permission_value' => 'admin',
        ]);

        WorkflowTransitionPermission::create([
            'transition_id' => $transition->id,
            'permission_type' => 'assignment',
        ]);

        $admin = $this->createTestUser(['role' => 'admin']);
        $order = $this->createOrder(['state' => 'pending']);

        // Admin passes via role (even though not assigned)
        $this->assertTrue($order->asUser($admin)->canTransitionTo('done'));
    }

    public function test_require_all_permissions(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'done']);
        $transition = $this->createWorkflowTransition($workflow, $s1, $s2);

        // Both role AND assignment required
        WorkflowTransitionPermission::create([
            'transition_id' => $transition->id,
            'permission_type' => 'role',
            'permission_value' => 'admin',
            'require_all' => true,
        ]);

        WorkflowTransitionPermission::create([
            'transition_id' => $transition->id,
            'permission_type' => 'assignment',
            'require_all' => true,
        ]);

        $admin = $this->createTestUser(['role' => 'admin']);
        $order = $this->createOrder(['state' => 'pending']);

        // Admin but not assigned → denied
        $this->assertFalse($order->asUser($admin)->canTransitionTo('done'));

        // Now assign
        $order->assignTo($admin, 'primary', $admin);

        // Admin AND assigned → allowed
        $this->assertTrue($order->asUser($admin)->canTransitionTo('done'));
    }

    public function test_no_user_denied_when_permissions_exist(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'done']);
        $transition = $this->createWorkflowTransition($workflow, $s1, $s2);

        WorkflowTransitionPermission::create([
            'transition_id' => $transition->id,
            'permission_type' => 'role',
            'permission_value' => 'admin',
        ]);

        $order = $this->createOrder(['state' => 'pending']);

        // No user set, no auth → denied
        $this->assertFalse($order->canTransitionTo('processing'));
    }

    public function test_force_transition_bypasses_access_enforcement(): void
    {
        config()->set('filament-flow.state_access.enforce_on_transition', true);

        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'done']);
        $this->createWorkflowTransition($workflow, $s1, $s2);

        $order = $this->createOrder(['state' => 'pending']);

        // forceTransitionTo bypasses enforcement
        $order->forceTransitionTo('done');
        $this->assertEquals('done', $order->state);
    }

    private function createOrder(array $data = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-PERM-'.uniqid(),
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ], $data));
    }
}
