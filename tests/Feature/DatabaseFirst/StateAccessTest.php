<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Exceptions\UnauthorizedTransitionException;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Services\WorkflowStateAccessService;
use RoBYCoNTe\FilamentFlow\Support\AccessRuleEvaluator;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Test state-based access control functionality
 */
class StateAccessTest extends TestCase
{
    protected WorkflowStateAccessService $accessService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accessService = new WorkflowStateAccessService;

        // Enable enforcement for access control tests
        config()->set('filament-flow.state_access.enforce_on_transition', true);
    }

    /**
     * Test public rule (*) allows everyone
     */
    public function test_public_rule_allows_everyone(): void
    {
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'view',
            'rule' => '*',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ACCESS-001',
            'customer_name' => 'John Doe',
            'total_amount' => 100.00,
            'state' => PendingState::class,
        ]);

        $user = $this->createTestUser();

        $this->assertTrue($order->canBeViewedBy($user));
    }

    /**
     * Test authenticated rule requires user
     */
    public function test_authenticated_rule_requires_user(): void
    {
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'view',
            'rule' => '@authenticated',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ACCESS-002',
            'customer_name' => 'Jane Doe',
            'total_amount' => 150.00,
            'state' => PendingState::class,
        ]);

        $user = $this->createTestUser();

        // With user - should allow
        $this->assertTrue($order->canBeViewedBy($user));

        // Without user (null) - should deny
        $this->assertFalse($order->canBeViewedBy(null));
    }

    /**
     * Test role-based rule
     */
    public function test_role_based_rule(): void
    {
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'edit',
            'rule' => 'role:admin,manager',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ACCESS-003',
            'customer_name' => 'Bob Smith',
            'total_amount' => 200.00,
            'state' => PendingState::class,
        ]);

        $admin = $this->createTestUser(['email' => 'admin@test.com', 'role' => 'admin']);
        $manager = $this->createTestUser(['email' => 'manager@test.com', 'role' => 'manager']);
        $operator = $this->createTestUser(['email' => 'operator@test.com', 'role' => 'operator']);

        $this->assertTrue($order->canBeEditedBy($admin));
        $this->assertTrue($order->canBeEditedBy($manager));
        $this->assertFalse($order->canBeEditedBy($operator));
    }

    /**
     * Test owner rule
     */
    public function test_owner_rule(): void
    {
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'edit',
            'rule' => '@owner',
        ]);

        $owner = $this->createTestUser(['email' => 'owner@test.com']);
        $otherUser = $this->createTestUser(['email' => 'other@test.com']);

        $order = Order::create([
            'order_number' => 'ORD-ACCESS-004',
            'customer_name' => 'Alice Johnson',
            'total_amount' => 250.00,
            'state' => PendingState::class,
            'user_id' => $owner->id,
        ]);

        $this->assertTrue($order->canBeEditedBy($owner));
        $this->assertFalse($order->canBeEditedBy($otherUser));
    }

    /**
     * Test assigned user rule
     */
    public function test_assigned_user_rule(): void
    {
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'view',
            'rule' => '@assigned',
        ]);

        $assignedUser = $this->createTestUser(['email' => 'assigned@test.com']);
        $unassignedUser = $this->createTestUser(['email' => 'unassigned@test.com']);

        $order = Order::create([
            'order_number' => 'ORD-ACCESS-005',
            'customer_name' => 'Charlie Brown',
            'total_amount' => 300.00,
            'state' => PendingState::class,
        ]);

        // Assign user to order
        $order->assignTo($assignedUser, 'primary');

        $this->assertTrue($order->canBeViewedBy($assignedUser));
        $this->assertFalse($order->canBeViewedBy($unassignedUser));
    }

    /**
     * Test assigned user with specific type rule
     */
    public function test_assigned_user_with_type_rule(): void
    {
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'edit',
            'rule' => '@assigned:primary',
        ]);

        $primaryUser = $this->createTestUser(['email' => 'primary@test.com']);
        $secondaryUser = $this->createTestUser(['email' => 'secondary@test.com']);

        $order = Order::create([
            'order_number' => 'ORD-ACCESS-006',
            'customer_name' => 'David Lee',
            'total_amount' => 350.00,
            'state' => PendingState::class,
        ]);

        $order->assignTo($primaryUser, 'primary');
        $order->assignTo($secondaryUser, 'secondary');

        $this->assertTrue($order->canBeEditedBy($primaryUser));
        $this->assertFalse($order->canBeEditedBy($secondaryUser));
    }

    /**
     * Test super admin bypasses all rules
     */
    public function test_super_admin_bypass(): void
    {
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        // Create very restrictive rule
        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'view',
            'rule' => 'role:nonexistent_role',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ACCESS-007',
            'customer_name' => 'Eve Martin',
            'total_amount' => 400.00,
            'state' => PendingState::class,
        ]);

        $superAdmin = $this->createTestUser(['email' => 'super@test.com', 'role' => 'super_admin']);
        $normalUser = $this->createTestUser(['email' => 'normal@test.com', 'role' => 'user']);

        $this->assertTrue($order->canBeViewedBy($superAdmin));
        $this->assertFalse($order->canBeViewedBy($normalUser));
    }

    /**
     * Test multiple rules with OR operator
     */
    public function test_multiple_rules_or_operator(): void
    {
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        // Admin OR assigned can view
        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'view',
            'rule' => 'role:admin',
            'operator' => 'or',
        ]);

        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'view',
            'rule' => '@assigned',
            'operator' => 'or',
        ]);

        $owner = $this->createTestUser(['email' => 'owner@test.com']);
        $admin = $this->createTestUser(['email' => 'admin@test.com', 'role' => 'admin']);
        $assignedUser = $this->createTestUser(['email' => 'assigned@test.com']);
        $randomUser = $this->createTestUser(['email' => 'random@test.com']);

        $order = Order::create([
            'order_number' => 'ORD-ACCESS-008',
            'customer_name' => 'Frank Wilson',
            'total_amount' => 450.00,
            'state' => PendingState::class,
            'user_id' => $owner->id,
        ]);

        $order->assignTo($assignedUser, 'primary');

        $this->assertTrue($order->canBeViewedBy($admin));
        $this->assertTrue($order->canBeViewedBy($assignedUser));
        $this->assertFalse($order->canBeViewedBy($randomUser));
    }

    /**
     * Test different access types (view, edit, transition)
     */
    public function test_different_access_types(): void
    {
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        // Everyone can view
        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'view',
            'rule' => '@authenticated',
        ]);

        // Only owner can edit
        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'edit',
            'rule' => '@owner',
        ]);

        // Only admin can transition
        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'transition',
            'rule' => 'role:admin',
        ]);

        $owner = $this->createTestUser(['email' => 'owner@test.com']);
        $admin = $this->createTestUser(['email' => 'admin@test.com', 'role' => 'admin']);
        $viewer = $this->createTestUser(['email' => 'viewer@test.com']);

        $order = Order::create([
            'order_number' => 'ORD-ACCESS-009',
            'customer_name' => 'Grace Taylor',
            'total_amount' => 500.00,
            'state' => PendingState::class,
            'user_id' => $owner->id,
        ]);

        // Viewer can view, but not edit or transition
        $this->assertTrue($order->canBeViewedBy($viewer));
        $this->assertFalse($order->canBeEditedBy($viewer));
        $this->assertFalse($order->canBeTransitionedBy($viewer));

        // Owner can view and edit, but not transition
        $this->assertTrue($order->canBeViewedBy($owner));
        $this->assertTrue($order->canBeEditedBy($owner));
        $this->assertFalse($order->canBeTransitionedBy($owner));

        // Admin can view (authenticated) and transition, but not edit (not owner)
        $this->assertTrue($order->canBeViewedBy($admin));
        $this->assertFalse($order->canBeEditedBy($admin));
        $this->assertTrue($order->canBeTransitionedBy($admin));
    }

    /**
     * Test access changes with state
     */
    public function test_access_changes_with_state(): void
    {
        $workflow = $this->createTestWorkflow();

        $pendingState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        $processingState = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'class_name' => ProcessingState::class,
        ]);

        // In pending: anyone can edit
        WorkflowStateAccessRule::create([
            'state_id' => $pendingState->id,
            'access_type' => 'edit',
            'rule' => '@authenticated',
        ]);

        // In processing: only admin can edit
        WorkflowStateAccessRule::create([
            'state_id' => $processingState->id,
            'access_type' => 'edit',
            'rule' => 'role:admin',
        ]);

        $user = $this->createTestUser(['email' => 'user@test.com', 'role' => 'user']);

        $order = Order::create([
            'order_number' => 'ORD-ACCESS-010',
            'customer_name' => 'Henry Adams',
            'total_amount' => 550.00,
            'state' => PendingState::class,
        ]);

        // In pending state - user can edit
        $this->assertTrue($order->canBeEditedBy($user));

        // Change to processing state
        $order->state = ProcessingState::class;
        $order->save();
        $order->refresh();

        // In processing state - user cannot edit
        $this->assertFalse($order->canBeEditedBy($user));
    }

    /**
     * Test default rules when no state-specific rules exist
     */
    public function test_default_rules_fallback(): void
    {
        // No workflow or rules created

        $order = Order::create([
            'order_number' => 'ORD-ACCESS-011',
            'customer_name' => 'Ivy Chen',
            'total_amount' => 600.00,
            'state' => PendingState::class,
        ]);

        $user = $this->createTestUser();

        // Default is @authenticated for all access types
        $this->assertTrue($order->canBeViewedBy($user));
        $this->assertTrue($order->canBeEditedBy($user));
        $this->assertTrue($order->canBeTransitionedBy($user));

        // Without user - defaults should deny (since @authenticated requires user)
        $this->assertFalse($order->canBeViewedBy(null));
    }

    /**
     * Test inactive rules are ignored
     */
    public function test_inactive_rules_ignored(): void
    {
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        // Create inactive rule that would deny access
        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'view',
            'rule' => 'role:nonexistent',
            'is_active' => false,
        ]);

        // Create active rule that allows
        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'view',
            'rule' => '@authenticated',
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ACCESS-012',
            'customer_name' => 'Jack Miller',
            'total_amount' => 650.00,
            'state' => PendingState::class,
        ]);

        $user = $this->createTestUser();

        // Active rule should apply
        $this->assertTrue($order->canBeViewedBy($user));
    }

    /**
     * Test AccessRuleEvaluator parseRule method
     */
    public function test_access_rule_evaluator_parse_rule(): void
    {
        $evaluator = new AccessRuleEvaluator;

        // Test various rule formats
        $this->assertEquals(['type' => 'all', 'value' => null], $evaluator->parseRule('*'));
        $this->assertEquals(['type' => 'authenticated', 'value' => null], $evaluator->parseRule('@authenticated'));
        $this->assertEquals(['type' => 'owner', 'value' => null], $evaluator->parseRule('@owner'));
        $this->assertEquals(['type' => 'assigned', 'value' => null], $evaluator->parseRule('@assigned'));
        $this->assertEquals(['type' => 'assigned', 'value' => 'primary'], $evaluator->parseRule('@assigned:primary'));
        $this->assertEquals(['type' => 'role', 'value' => 'admin'], $evaluator->parseRule('role:admin'));
        $this->assertEquals(['type' => 'role', 'value' => 'admin,manager'], $evaluator->parseRule('role:admin,manager'));
        $this->assertEquals(['type' => 'permission', 'value' => 'edit-orders'], $evaluator->parseRule('permission:edit-orders'));
    }

    /**
     * Test WorkflowStateAccessRule model helpers
     */
    public function test_access_rule_model_helpers(): void
    {
        $workflow = $this->createTestWorkflow();
        $state = $this->createWorkflowState($workflow, ['name' => 'test']);

        // Public rule
        $publicRule = WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'view',
            'rule' => '*',
        ]);
        $this->assertTrue($publicRule->isPublic());

        // Role rule
        $roleRule = WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'view',
            'rule' => 'role:admin,manager',
        ]);
        $this->assertTrue($roleRule->isRole());
        $this->assertEquals(['admin', 'manager'], $roleRule->getRoles());

        // Assignment rule
        $assignmentRule = WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'view',
            'rule' => '@assigned:primary',
        ]);
        $this->assertTrue($assignmentRule->isAssigned());
        $this->assertEquals('primary', $assignmentRule->getAssignmentType());

        // Permission rule
        $permissionRule = WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'view',
            'rule' => 'permission:edit-orders',
        ]);
        $this->assertTrue($permissionRule->isPermission());
        $this->assertEquals('edit-orders', $permissionRule->getPermission());
    }

    /**
     * Test getStateAccessRules returns correct rules
     */
    public function test_get_state_access_rules(): void
    {
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'view',
            'rule' => 'role:admin',
        ]);

        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'view',
            'rule' => '@assigned',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ACCESS-013',
            'customer_name' => 'Kate Williams',
            'total_amount' => 700.00,
            'state' => PendingState::class,
        ]);

        $rules = $order->getStateAccessRules('view');

        $this->assertContains('role:admin', $rules);
        $this->assertContains('@assigned', $rules);
    }

    /**
     * Test disabled state access control allows all
     */
    public function test_disabled_access_control_allows_all(): void
    {
        // Disable state access control
        config()->set('filament-flow.state_access.enabled', false);

        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        // Create very restrictive rule
        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'view',
            'rule' => 'role:nonexistent',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ACCESS-014',
            'customer_name' => 'Leo Martinez',
            'total_amount' => 750.00,
            'state' => PendingState::class,
        ]);

        $user = $this->createTestUser(['role' => 'user']);

        // With access control disabled, everyone has access
        $this->assertTrue($order->canBeViewedBy($user));

        // Re-enable for other tests
        config()->set('filament-flow.state_access.enabled', true);
    }

    /**
     * Test complete transition flow with access control enforcement
     *
     * Scenario: User with role "user" tries to transition an order but
     * only "manager" role is allowed to transition from pending state
     */
    public function test_transition_blocked_for_unauthorized_user(): void
    {
        $workflow = $this->createTestWorkflow();

        $pendingState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
            'is_initial' => true,
        ]);

        $processingState = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'class_name' => ProcessingState::class,
        ]);

        // Create transition in database
        $this->createWorkflowTransition($workflow, $pendingState, $processingState, [
            'name' => 'process',
            'label' => 'Process Order',
        ]);

        // Only managers can transition from pending state
        WorkflowStateAccessRule::create([
            'state_id' => $pendingState->id,
            'access_type' => 'transition',
            'rule' => 'role:manager',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ACCESS-015',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'state' => PendingState::class,
        ]);

        // Create users with different roles
        $normalUser = $this->createTestUser(['email' => 'user@test.com', 'role' => 'user']);
        $manager = $this->createTestUser(['email' => 'manager@test.com', 'role' => 'manager']);

        // Normal user cannot transition
        $this->assertFalse($order->canBeTransitionedBy($normalUser));

        // Manager can transition
        $this->assertTrue($order->canBeTransitionedBy($manager));

        // With automatic enforcement, unauthorized transition throws exception
        $exceptionThrown = false;
        try {
            $order->asUser($normalUser)->transitionTo(ProcessingState::class);
        } catch (UnauthorizedTransitionException) {
            $exceptionThrown = true;
        }

        $this->assertTrue($exceptionThrown, 'Expected UnauthorizedTransitionException for normal user');

        // Order should still be in pending state (transition was blocked)
        $order->refresh();
        $this->assertEquals(PendingState::class, get_class($order->state));

        // Now try with manager - transition should succeed
        $order->asUser($manager)->transitionTo(ProcessingState::class);

        // Order should now be in processing state
        $order->refresh();
        $this->assertEquals(ProcessingState::class, get_class($order->state));
    }

    /**
     * Test transition to specific state with different access rules
     *
     * Scenario: Different target states have different access requirements
     */
    public function test_transition_to_specific_state_access(): void
    {
        $workflow = $this->createTestWorkflow();

        $pendingState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
            'is_initial' => true,
        ]);

        $processingState = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'class_name' => ProcessingState::class,
        ]);

        // Create transitions
        $this->createWorkflowTransition($workflow, $pendingState, $processingState);

        // Anyone authenticated can transition from pending
        WorkflowStateAccessRule::create([
            'state_id' => $pendingState->id,
            'access_type' => 'transition',
            'rule' => '@authenticated',
        ]);

        // Only admin can transition FROM processing
        WorkflowStateAccessRule::create([
            'state_id' => $processingState->id,
            'access_type' => 'transition',
            'rule' => 'role:admin',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ACCESS-016',
            'customer_name' => 'Test Customer 2',
            'total_amount' => 200.00,
            'state' => PendingState::class,
        ]);

        $normalUser = $this->createTestUser(['email' => 'normal@test.com', 'role' => 'user']);
        $admin = $this->createTestUser(['email' => 'admin@test.com', 'role' => 'admin']);

        // Normal user can transition from pending
        $this->assertTrue($order->canBeTransitionedBy($normalUser));

        // Perform transition with the normal user (they have permission from pending)
        $order->asUser($normalUser)->transitionTo(ProcessingState::class);
        $order->refresh();

        // Now in processing state - normal user cannot transition further
        $this->assertFalse($order->canBeTransitionedBy($normalUser));

        // But admin can
        $this->assertTrue($order->canBeTransitionedBy($admin));
    }

    /**
     * Test query scope for accessible records in transition context
     */
    public function test_scope_records_user_can_transition(): void
    {
        $workflow = $this->createTestWorkflow();

        $pendingState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        // Only assigned users can transition
        WorkflowStateAccessRule::create([
            'state_id' => $pendingState->id,
            'access_type' => 'transition',
            'rule' => '@assigned',
        ]);

        $user1 = $this->createTestUser(['email' => 'user1@test.com']);
        $user2 = $this->createTestUser(['email' => 'user2@test.com']);

        // Create orders
        $order1 = Order::create([
            'order_number' => 'ORD-ACCESS-017',
            'customer_name' => 'Customer 1',
            'total_amount' => 100.00,
            'state' => PendingState::class,
        ]);
        $order1->assignTo($user1, 'primary');

        $order2 = Order::create([
            'order_number' => 'ORD-ACCESS-018',
            'customer_name' => 'Customer 2',
            'total_amount' => 200.00,
            'state' => PendingState::class,
        ]);
        $order2->assignTo($user2, 'primary');

        $order3 = Order::create([
            'order_number' => 'ORD-ACCESS-019',
            'customer_name' => 'Customer 3',
            'total_amount' => 300.00,
            'state' => PendingState::class,
        ]);
        // Not assigned to anyone

        // User1 can only transition order1
        $this->assertTrue($order1->canBeTransitionedBy($user1));
        $this->assertFalse($order2->canBeTransitionedBy($user1));
        $this->assertFalse($order3->canBeTransitionedBy($user1));

        // User2 can only transition order2
        $this->assertFalse($order1->canBeTransitionedBy($user2));
        $this->assertTrue($order2->canBeTransitionedBy($user2));
        $this->assertFalse($order3->canBeTransitionedBy($user2));
    }

    /**
     * Test owner-based transition access with state changes
     */
    public function test_owner_can_transition_own_records_only(): void
    {
        $workflow = $this->createTestWorkflow();

        $pendingState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        $processingState = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'class_name' => ProcessingState::class,
        ]);

        $this->createWorkflowTransition($workflow, $pendingState, $processingState);

        // Only owner can transition
        WorkflowStateAccessRule::create([
            'state_id' => $pendingState->id,
            'access_type' => 'transition',
            'rule' => '@owner',
        ]);

        $owner = $this->createTestUser(['email' => 'owner@test.com']);
        $otherUser = $this->createTestUser(['email' => 'other@test.com']);

        $order = Order::create([
            'order_number' => 'ORD-ACCESS-020',
            'customer_name' => 'Owner Customer',
            'total_amount' => 500.00,
            'state' => PendingState::class,
            'user_id' => $owner->id,
        ]);

        // Other user cannot transition (not the owner)
        $this->assertFalse($order->canBeTransitionedBy($otherUser));

        // Owner can transition
        $this->assertTrue($order->canBeTransitionedBy($owner));

        // With automatic enforcement, other user's transition throws exception
        $exceptionThrown = false;
        try {
            $order->asUser($otherUser)->transitionTo(ProcessingState::class);
        } catch (UnauthorizedTransitionException) {
            $exceptionThrown = true;
        }

        $this->assertTrue($exceptionThrown, 'Expected UnauthorizedTransitionException for non-owner');
        $order->refresh();
        $this->assertEquals(PendingState::class, get_class($order->state));

        // Owner's transition succeeds
        $order->asUser($owner)->transitionTo(ProcessingState::class);

        $order->refresh();
        $this->assertEquals(ProcessingState::class, get_class($order->state));

        // Verify transition was logged
        $this->assertTransitionLogged($order, PendingState::class, ProcessingState::class);
    }

    /**
     * Test automatic access control enforcement throws exception for unauthorized user
     */
    public function test_automatic_enforcement_throws_exception(): void
    {
        $workflow = $this->createTestWorkflow();

        $pendingState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
            'is_initial' => true,
        ]);

        $processingState = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'class_name' => ProcessingState::class,
        ]);

        $this->createWorkflowTransition($workflow, $pendingState, $processingState);

        // Only managers can transition
        WorkflowStateAccessRule::create([
            'state_id' => $pendingState->id,
            'access_type' => 'transition',
            'rule' => 'role:manager',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ENFORCE-001',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'state' => PendingState::class,
        ]);

        $normalUser = $this->createTestUser(['email' => 'user@test.com', 'role' => 'user']);

        // Expect exception when unauthorized user tries to transition
        $this->expectException(UnauthorizedTransitionException::class);

        // This should throw UnauthorizedTransitionException
        $order->asUser($normalUser)->transitionTo(ProcessingState::class);
    }

    /**
     * Test automatic enforcement allows authorized user
     */
    public function test_automatic_enforcement_allows_authorized_user(): void
    {
        $workflow = $this->createTestWorkflow();

        $pendingState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
            'is_initial' => true,
        ]);

        $processingState = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'class_name' => ProcessingState::class,
        ]);

        $this->createWorkflowTransition($workflow, $pendingState, $processingState);

        // Only managers can transition
        WorkflowStateAccessRule::create([
            'state_id' => $pendingState->id,
            'access_type' => 'transition',
            'rule' => 'role:manager',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ENFORCE-002',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'state' => PendingState::class,
        ]);

        $manager = $this->createTestUser(['email' => 'manager@test.com', 'role' => 'manager']);

        // Manager should be able to transition without exception
        $order->asUser($manager)->transitionTo(ProcessingState::class);

        $order->refresh();
        $this->assertEquals(ProcessingState::class, get_class($order->state));
    }

    /**
     * Test forceTransitionTo bypasses access control
     */
    public function test_force_transition_bypasses_access_control(): void
    {
        $workflow = $this->createTestWorkflow();

        $pendingState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
            'is_initial' => true,
        ]);

        $processingState = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'class_name' => ProcessingState::class,
        ]);

        $this->createWorkflowTransition($workflow, $pendingState, $processingState);

        // Only managers can transition
        WorkflowStateAccessRule::create([
            'state_id' => $pendingState->id,
            'access_type' => 'transition',
            'rule' => 'role:manager',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ENFORCE-003',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'state' => PendingState::class,
        ]);

        $normalUser = $this->createTestUser(['email' => 'user@test.com', 'role' => 'user']);

        // Even though user is not authorized, forceTransitionTo should work
        $order->asUser($normalUser)->forceTransitionTo(ProcessingState::class);

        $order->refresh();
        $this->assertEquals(ProcessingState::class, get_class($order->state));
    }

    /**
     * Test enforcement can be disabled via config
     */
    public function test_enforcement_can_be_disabled(): void
    {
        // Disable enforcement
        config()->set('filament-flow.state_access.enforce_on_transition', false);

        $workflow = $this->createTestWorkflow();

        $pendingState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
            'is_initial' => true,
        ]);

        $processingState = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'class_name' => ProcessingState::class,
        ]);

        $this->createWorkflowTransition($workflow, $pendingState, $processingState);

        // Only managers can transition
        WorkflowStateAccessRule::create([
            'state_id' => $pendingState->id,
            'access_type' => 'transition',
            'rule' => 'role:manager',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ENFORCE-004',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'state' => PendingState::class,
        ]);

        $normalUser = $this->createTestUser(['email' => 'user@test.com', 'role' => 'user']);

        // With enforcement disabled, transition should succeed even for unauthorized user
        $order->asUser($normalUser)->transitionTo(ProcessingState::class);

        $order->refresh();
        $this->assertEquals(ProcessingState::class, get_class($order->state));

        // Re-enable for other tests
        config()->set('filament-flow.state_access.enforce_on_transition', true);
    }

    /**
     * Test exception contains useful information
     *
     * @noinspection PhpPossiblePolymorphicInvocationInspection
     */
    public function test_exception_contains_useful_info(): void
    {
        $workflow = $this->createTestWorkflow();

        $pendingState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
            'is_initial' => true,
        ]);

        $processingState = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'class_name' => ProcessingState::class,
        ]);

        $this->createWorkflowTransition($workflow, $pendingState, $processingState);

        // Only managers can transition
        WorkflowStateAccessRule::create([
            'state_id' => $pendingState->id,
            'access_type' => 'transition',
            'rule' => 'role:manager',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ENFORCE-005',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'state' => PendingState::class,
        ]);

        $normalUser = $this->createTestUser(['email' => 'user@test.com', 'role' => 'user']);

        try {
            $order->asUser($normalUser)->transitionTo(ProcessingState::class);
            $this->fail('Expected UnauthorizedTransitionException was not thrown');
        } catch (UnauthorizedTransitionException $e) {
            // Verify exception contains all necessary information
            $this->assertSame($order->id, $e->getRecord()->id);
            $this->assertEquals(PendingState::class, $e->getFromState());
            $this->assertEquals(ProcessingState::class, $e->getToState());
            $this->assertSame($normalUser->id, $e->getUser()->id);

            // Verify message is descriptive
            $message = $e->getMessage();
            $this->assertStringContainsString('User #'.$normalUser->id, $message);
            $this->assertStringContainsString('Order', $message);
            $this->assertStringContainsString('PendingState', $message);
            $this->assertStringContainsString('ProcessingState', $message);
        }
    }

    /**
     * Test transition without authenticated user throws exception with appropriate message
     */
    public function test_exception_for_unauthenticated_user(): void
    {
        $workflow = $this->createTestWorkflow();

        $pendingState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
            'is_initial' => true,
        ]);

        $processingState = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'class_name' => ProcessingState::class,
        ]);

        $this->createWorkflowTransition($workflow, $pendingState, $processingState);

        // Only authenticated users can transition
        WorkflowStateAccessRule::create([
            'state_id' => $pendingState->id,
            'access_type' => 'transition',
            'rule' => '@authenticated',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ENFORCE-006',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'state' => PendingState::class,
        ]);

        try {
            // Transition without any user
            $order->asUser(null)->transitionTo(ProcessingState::class);
            $this->fail('Expected UnauthorizedTransitionException was not thrown');
        } catch (UnauthorizedTransitionException $e) {
            $this->assertNull($e->getUser());
            $this->assertStringContainsString('No authenticated user', $e->getMessage());
        }
    }
}
