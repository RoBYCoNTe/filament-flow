<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Services\WorkflowStateAccessService;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class WorkflowStateAccessServiceTest extends TestCase
{
    private WorkflowStateAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WorkflowStateAccessService::class);
    }

    public function test_is_enabled_by_default(): void
    {
        $this->assertTrue($this->service->isEnabled());
    }

    public function test_is_disabled_when_config_set(): void
    {
        config(['filament-flow.state_access.enabled' => false]);
        $this->assertFalse($this->service->isEnabled());
    }

    public function test_can_view_without_rules_uses_defaults(): void
    {
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, ['name' => 'pending']);

        $order = Order::create([
            'order_number' => 'ORD-ACC-001',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'pending',
        ]);

        $user = $this->createTestUser();

        // Default is @authenticated → any logged in user can view
        $this->assertTrue($this->service->canView($order, $user));
    }

    public function test_can_view_with_role_rule(): void
    {
        $workflow = $this->createTestWorkflow();
        $state = $this->createWorkflowState($workflow, ['name' => 'pending']);

        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'view',
            'rule' => 'role:admin',
            'priority' => 0,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ACC-002',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'pending',
        ]);

        $admin = $this->createTestUser(['role' => 'admin']);
        $editor = $this->createTestUser(['email' => 'editor@test.com', 'role' => 'editor']);

        $this->assertTrue($this->service->canView($order, $admin));
        $this->assertFalse($this->service->canView($order, $editor));
    }

    public function test_can_edit_with_authenticated_rule(): void
    {
        $workflow = $this->createTestWorkflow();
        $state = $this->createWorkflowState($workflow, ['name' => 'draft']);

        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'edit',
            'rule' => '@authenticated',
            'priority' => 0,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ACC-003',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'draft',
        ]);

        $user = $this->createTestUser();

        $this->assertTrue($this->service->canEdit($order, $user));
    }

    public function test_can_transition_checks_state_access(): void
    {
        $workflow = $this->createTestWorkflow();
        $state = $this->createWorkflowState($workflow, ['name' => 'pending']);

        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'transition',
            'rule' => 'role:admin',
            'priority' => 0,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ACC-004',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'pending',
        ]);

        $admin = $this->createTestUser(['role' => 'admin']);
        $editor = $this->createTestUser(['email' => 'editor@test.com', 'role' => 'editor']);

        $this->assertTrue($this->service->canTransition($order, $admin));
        $this->assertFalse($this->service->canTransition($order, $editor));
    }

    public function test_disabled_access_control_allows_everything(): void
    {
        config(['filament-flow.state_access.enabled' => false]);

        $workflow = $this->createTestWorkflow();
        $state = $this->createWorkflowState($workflow, ['name' => 'locked']);

        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'edit',
            'rule' => 'role:admin',
            'priority' => 0,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ACC-005',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'locked',
        ]);

        $editor = $this->createTestUser(['role' => 'editor']);

        // Even without matching role, access is granted when disabled
        $this->assertTrue($this->service->canEdit($order, $editor));
    }

    public function test_can_create_with_authenticated_rule(): void
    {
        $workflow = $this->createTestWorkflow();
        $initialState = $this->createWorkflowState($workflow, [
            'name' => 'draft',
            'is_initial' => true,
        ]);

        WorkflowStateAccessRule::create([
            'state_id' => $initialState->id,
            'access_type' => 'create',
            'rule' => '@authenticated',
            'priority' => 0,
            'is_active' => true,
        ]);

        $user = $this->createTestUser();

        $this->assertTrue($this->service->canCreate(Order::class, $user));
    }

    public function test_can_create_denied_with_wrong_role(): void
    {
        $workflow = $this->createTestWorkflow();
        $initialState = $this->createWorkflowState($workflow, [
            'name' => 'draft',
            'is_initial' => true,
        ]);

        WorkflowStateAccessRule::create([
            'state_id' => $initialState->id,
            'access_type' => 'create',
            'rule' => 'role:admin',
            'priority' => 0,
            'is_active' => true,
        ]);

        $editor = $this->createTestUser(['role' => 'editor']);

        $this->assertFalse($this->service->canCreate(Order::class, $editor));
    }

    public function test_can_create_without_workflow_uses_defaults(): void
    {
        $user = $this->createTestUser();

        // No workflow for this model → defaults to @authenticated
        $this->assertTrue($this->service->canCreate(Order::class, $user));
    }

    public function test_public_rule_allows_null_user(): void
    {
        $workflow = $this->createTestWorkflow();
        $state = $this->createWorkflowState($workflow, ['name' => 'public_state']);

        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'view',
            'rule' => '*',
            'priority' => 0,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ACC-006',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'public_state',
        ]);

        $this->assertTrue($this->service->canView($order, null));
    }

    public function test_inactive_rules_are_ignored(): void
    {
        $workflow = $this->createTestWorkflow();
        $state = $this->createWorkflowState($workflow, ['name' => 'pending']);

        // Inactive rule — should be ignored
        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'view',
            'rule' => 'role:admin',
            'priority' => 0,
            'is_active' => false,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ACC-007',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'pending',
        ]);

        $user = $this->createTestUser(['role' => 'editor']);

        // No active rules → falls back to defaults (@authenticated) → allowed
        $this->assertTrue($this->service->canView($order, $user));
    }

    public function test_get_access_rules_returns_db_rules(): void
    {
        $workflow = $this->createTestWorkflow();
        $state = $this->createWorkflowState($workflow, ['name' => 'draft']);

        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'view',
            'rule' => 'role:admin',
            'priority' => 0,
            'is_active' => true,
        ]);

        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'view',
            'rule' => '@authenticated',
            'priority' => 1,
            'is_active' => true,
        ]);

        $rules = $this->service->getAccessRules('draft', 'view');

        $this->assertContains('role:admin', $rules);
        $this->assertContains('@authenticated', $rules);
    }

    public function test_scope_accessible_separates_free_and_assigned_states(): void
    {
        $workflow = $this->createTestWorkflow();
        $freeState = $this->createWorkflowState($workflow, ['name' => 'open']);
        $assignedState = $this->createWorkflowState($workflow, ['name' => 'in_progress']);

        // 'open' state: accessible by any authenticated user
        WorkflowStateAccessRule::create([
            'state_id' => $freeState->id,
            'access_type' => 'view',
            'rule' => '@authenticated',
            'priority' => 0,
            'is_active' => true,
        ]);

        // 'in_progress' state: only @assigned
        WorkflowStateAccessRule::create([
            'state_id' => $assignedState->id,
            'access_type' => 'view',
            'rule' => '@assigned',
            'priority' => 0,
            'is_active' => true,
        ]);

        $admin = $this->createTestUser(['role' => 'admin']);
        $user = $this->createTestUser(['email' => 'user@test.com', 'role' => 'viewer']);

        // Create orders in both states
        $openOrder = Order::create([
            'order_number' => 'ORD-SCOPE-001',
            'customer_name' => 'Free',
            'total_amount' => 100,
            'state' => 'open',
        ]);

        $assignedOrder = Order::create([
            'order_number' => 'ORD-SCOPE-002',
            'customer_name' => 'Assigned',
            'total_amount' => 200,
            'state' => 'in_progress',
        ]);

        // Assign the user to the assigned order
        $assignedOrder->assignTo($user, 'primary', $admin);

        // User should see open order (free state) + assigned order (assigned to them)
        $visible = $this->service->scopeAccessible(Order::query(), $user)->pluck('order_number')->toArray();
        $this->assertContains('ORD-SCOPE-001', $visible);
        $this->assertContains('ORD-SCOPE-002', $visible);

        // Admin (not assigned) should see open order but also in_progress
        // because the scope includes @assigned states but admin isn't assigned
        $visibleAdmin = $this->service->scopeAccessible(Order::query(), $admin)->pluck('order_number')->toArray();
        $this->assertContains('ORD-SCOPE-001', $visibleAdmin);
        // Admin should NOT see in_progress order since they're not assigned
        $this->assertNotContains('ORD-SCOPE-002', $visibleAdmin);
    }

    public function test_and_operator_rules(): void
    {
        $workflow = $this->createTestWorkflow();
        $state = $this->createWorkflowState($workflow, ['name' => 'restricted']);

        // Require role:admin AND @authenticated (both must pass)
        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'edit',
            'rule' => 'role:admin',
            'operator' => 'and',
            'priority' => 0,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ACC-008',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'restricted',
        ]);

        $admin = $this->createTestUser(['role' => 'admin']);
        $editor = $this->createTestUser(['email' => 'editor@test.com', 'role' => 'editor']);

        $this->assertTrue($this->service->canEdit($order, $admin));
        $this->assertFalse($this->service->canEdit($order, $editor));
    }
}
