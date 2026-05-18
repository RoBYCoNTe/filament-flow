<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\CodeFirst;

use RoBYCoNTe\FilamentFlow\Contracts\HasAccessRules;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Services\WorkflowStateAccessService;
use RoBYCoNTe\FilamentFlow\Support\AccessRuleEvaluator;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\RestrictedState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Test Code-First access rules (defined in PHP State classes)
 */
class StateAccessRulesTest extends TestCase
{
    protected WorkflowStateAccessService $accessService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accessService = new WorkflowStateAccessService;
    }

    /**
     * Test that RestrictedState implements HasAccessRules
     */
    public function test_restricted_state_implements_interface(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-CF-001',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'state' => RestrictedState::class,
        ]);

        $this->assertInstanceOf(HasAccessRules::class, $order->state);
    }

    /**
     * Test Code-First view access rules
     */
    public function test_code_first_view_access(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-CF-002',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'state' => RestrictedState::class,
        ]);

        $user = $this->createTestUser();

        // @authenticated - any authenticated user can view
        $this->assertTrue($order->canBeViewedBy($user));

        // No user - cannot view
        $this->assertFalse($order->canBeViewedBy(null));
    }

    /**
     * Test Code-First edit access rules with @owner
     */
    public function test_code_first_edit_access_owner(): void
    {
        $owner = $this->createTestUser(['email' => 'owner@test.com']);
        $otherUser = $this->createTestUser(['email' => 'other@test.com']);

        $order = Order::create([
            'order_number' => 'ORD-CF-003',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'state' => RestrictedState::class,
            'user_id' => $owner->id,
        ]);

        // Owner can edit (rule: @owner)
        $this->assertTrue($order->canBeEditedBy($owner));

        // Non-owner cannot edit (unless assigned)
        $this->assertFalse($order->canBeEditedBy($otherUser));
    }

    /**
     * Test Code-First edit access rules with @assigned
     */
    public function test_code_first_edit_access_assigned(): void
    {
        $assignedUser = $this->createTestUser(['email' => 'assigned@test.com']);
        $unassignedUser = $this->createTestUser(['email' => 'unassigned@test.com']);

        $order = Order::create([
            'order_number' => 'ORD-CF-004',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'state' => RestrictedState::class,
        ]);

        // Assign user to order
        $order->assignTo($assignedUser, 'primary');

        // Assigned user can edit (rule: @assigned)
        $this->assertTrue($order->canBeEditedBy($assignedUser));

        // Unassigned user cannot edit
        $this->assertFalse($order->canBeEditedBy($unassignedUser));
    }

    /**
     * Test Code-First transition access rules with role
     */
    public function test_code_first_transition_access_role(): void
    {
        $manager = $this->createTestUser(['email' => 'manager@test.com', 'role' => 'manager']);
        $admin = $this->createTestUser(['email' => 'admin@test.com', 'role' => 'admin']);
        $normalUser = $this->createTestUser(['email' => 'user@test.com', 'role' => 'user']);

        $order = Order::create([
            'order_number' => 'ORD-CF-005',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'state' => RestrictedState::class,
        ]);

        // Manager can transition (rule: role:manager,admin)
        $this->assertTrue($order->canBeTransitionedBy($manager));

        // Admin can transition (rule: role:manager,admin)
        $this->assertTrue($order->canBeTransitionedBy($admin));

        // Normal user cannot transition
        $this->assertFalse($order->canBeTransitionedBy($normalUser));
    }

    /**
     * Test super admin bypasses Code-First rules
     */
    public function test_super_admin_bypasses_code_first_rules(): void
    {
        $superAdmin = $this->createTestUser(['email' => 'super@test.com', 'role' => 'super_admin']);

        $order = Order::create([
            'order_number' => 'ORD-CF-006',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'state' => RestrictedState::class,
        ]);

        // Super admin bypasses all rules
        $this->assertTrue($order->canBeViewedBy($superAdmin));
        $this->assertTrue($order->canBeEditedBy($superAdmin));
        $this->assertTrue($order->canBeTransitionedBy($superAdmin));
    }

    /**
     * Test getAccessRules returns Code-First rules
     */
    public function test_get_access_rules_returns_code_first_rules(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-CF-007',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'state' => RestrictedState::class,
        ]);

        $viewRules = $order->getStateAccessRules('view');
        $editRules = $order->getStateAccessRules('edit');
        $transitionRules = $order->getStateAccessRules('transition');

        $this->assertContains('@authenticated', $viewRules);
        $this->assertContains('@owner', $editRules);
        $this->assertContains('@assigned', $editRules);
        $this->assertContains('role:manager,admin', $transitionRules);
    }

    /**
     * Test state without HasAccessRules uses default rules
     */
    public function test_state_without_interface_uses_defaults(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-CF-008',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'state' => PendingState::class, // Does not implement HasAccessRules
        ]);

        $user = $this->createTestUser();

        // PendingState doesn't implement HasAccessRules, so default rules apply
        // Default is @authenticated for all access types
        $this->assertTrue($order->canBeViewedBy($user));
        $this->assertTrue($order->canBeEditedBy($user));
        $this->assertTrue($order->canBeTransitionedBy($user));
    }

    /**
     * Test Code-First rules have priority over database rules
     */
    public function test_code_first_rules_have_priority(): void
    {
        // Create workflow and database rules that would allow everyone
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, [
            'name' => 'restricted',
            'class_name' => RestrictedState::class,
        ]);

        // Database rule: everyone can transition (*)
        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'transition',
            'rule' => '*',
        ]);

        $normalUser = $this->createTestUser(['email' => 'user@test.com', 'role' => 'user']);

        $order = Order::create([
            'order_number' => 'ORD-CF-009',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'state' => RestrictedState::class,
        ]);

        // Even though database rule allows everyone,
        // Code-First rules take priority and only allow managers/admins
        $this->assertFalse($order->canBeTransitionedBy($normalUser));

        // Manager can still transition (satisfies Code-First rule)
        $manager = $this->createTestUser(['email' => 'manager@test.com', 'role' => 'manager']);
        $this->assertTrue($order->canBeTransitionedBy($manager));
    }

    /**
     * Test OR logic for multiple Code-First rules
     */
    public function test_code_first_rules_use_or_logic(): void
    {
        $owner = $this->createTestUser(['email' => 'owner@test.com']);
        $assignedUser = $this->createTestUser(['email' => 'assigned@test.com']);
        $randomUser = $this->createTestUser(['email' => 'random@test.com']);

        $order = Order::create([
            'order_number' => 'ORD-CF-010',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'state' => RestrictedState::class,
            'user_id' => $owner->id,
        ]);

        $order->assignTo($assignedUser, 'primary');

        // Edit rules are [@owner, @assigned] with OR logic
        // Owner can edit
        $this->assertTrue($order->canBeEditedBy($owner));

        // Assigned user can edit
        $this->assertTrue($order->canBeEditedBy($assignedUser));

        // Random user cannot edit (neither owner nor assigned)
        $this->assertFalse($order->canBeEditedBy($randomUser));
    }

    /**
     * Test empty rules array denies access
     */
    public function test_empty_rules_deny_access(): void
    {
        // Test empty rules evaluation directly via the service
        // since we can't easily create a model with an unregistered state class
        $evaluator = new AccessRuleEvaluator;
        $user = $this->createTestUser();

        $order = Order::create([
            'order_number' => 'ORD-CF-011',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'state' => PendingState::class,
        ]);

        // Empty rules with OR logic should return false
        $result = $evaluator->evaluateRules([], 'or', $user, $order);
        $this->assertFalse($result);
    }

    /**
     * Test Code-First CREATE access rules with roles
     */
    public function test_code_first_create_access_with_roles(): void
    {
        // Create a workflow with RestrictedState as initial state
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, [
            'name' => 'restricted',
            'class_name' => RestrictedState::class,
            'is_initial' => true,
        ]);

        // Sales role can create (rule: role:sales,admin)
        $salesUser = $this->createTestUser(['email' => 'sales@test.com', 'role' => 'sales']);
        $this->assertTrue(Order::canBeCreatedBy($salesUser));

        // Admin role can create (rule: role:sales,admin)
        $adminUser = $this->createTestUser(['email' => 'admin@test.com', 'role' => 'admin']);
        $this->assertTrue(Order::canBeCreatedBy($adminUser));

        // Normal user cannot create
        $normalUser = $this->createTestUser(['email' => 'user@test.com', 'role' => 'user']);
        $this->assertFalse(Order::canBeCreatedBy($normalUser));
    }

    /**
     * Test CREATE access rules - unauthenticated user
     */
    public function test_create_access_denied_for_unauthenticated(): void
    {
        // Create a workflow with RestrictedState as initial state
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, [
            'name' => 'restricted',
            'class_name' => RestrictedState::class,
            'is_initial' => true,
        ]);

        // Unauthenticated user cannot create
        $this->assertFalse(Order::canBeCreatedBy());
    }

    /**
     * Test CREATE access rules - super admin bypasses rules
     */
    public function test_super_admin_bypasses_create_rules(): void
    {
        // Create a workflow with RestrictedState as initial state
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, [
            'name' => 'restricted',
            'class_name' => RestrictedState::class,
            'is_initial' => true,
        ]);

        // Super admin can always create
        $superAdmin = $this->createTestUser(['email' => 'super@test.com', 'role' => 'super_admin']);
        $this->assertTrue(Order::canBeCreatedBy($superAdmin));
    }

    /**
     * Test CREATE access with default rules when no Code-First rules exist
     */
    public function test_create_access_uses_defaults_when_no_code_first(): void
    {
        // Create a workflow with PendingState (doesn't implement HasAccessRules)
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
            'is_initial' => true,
        ]);

        // Default is @authenticated, so any authenticated user can create
        $user = $this->createTestUser();
        $this->assertTrue(Order::canBeCreatedBy($user));

        // Unauthenticated cannot create with default @authenticated rule
        $this->assertFalse(Order::canBeCreatedBy());
    }

    /**
     * Test getCreateAccessRules returns correct rules
     */
    public function test_get_create_access_rules(): void
    {
        // RestrictedState defines: ['role:sales,admin']
        $rules = RestrictedState::getCreateAccessRules();

        $this->assertContains('role:sales,admin', $rules);
        $this->assertCount(1, $rules);
    }

    /**
     * Test getStateAccessRules returns CREATE rules for initial state
     */
    public function test_get_state_access_rules_returns_create_rules(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-CF-012',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'state' => RestrictedState::class,
        ]);

        $createRules = $order->getStateAccessRules('create');

        $this->assertContains('role:sales,admin', $createRules);
    }
}
