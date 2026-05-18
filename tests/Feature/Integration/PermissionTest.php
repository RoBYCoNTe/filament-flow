<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Integration;

use Illuminate\Support\Facades\Auth;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\RestrictedState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Permission Integration Tests
 *
 * Tests the integration of state-based access control with
 * application-level permissions and policies.
 */
class PermissionTest extends TestCase
{
    /**
     * Create an order with the specified state
     */
    private function createOrderInState(string $stateClass, array $attributes = []): Order
    {
        $order = Order::create(array_merge([
            'order_number' => 'ORD-PERM-'.uniqid(),
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ], $attributes));

        $order->state = new $stateClass($order);
        $order->save();
        $order->refresh();

        return $order;
    }

    // ===========================================
    // STATE-BASED ACCESS CONTROL TESTS
    // ===========================================

    /**
     * Test that state access checking works with authenticated user
     */
    public function test_state_access_with_authenticated_user(): void
    {
        $user = $this->createTestUser(['email' => 'auth@test.com']);
        $order = $this->createOrderInState(PendingState::class);

        // PendingState uses default rules (@authenticated)
        $this->assertTrue($order->canBeViewedBy($user));
        $this->assertTrue($order->canBeEditedBy($user));
    }

    /**
     * Test that state access checking denies unauthenticated users
     */
    public function test_state_access_denies_unauthenticated(): void
    {
        $order = $this->createOrderInState(PendingState::class);

        // Default rules require @authenticated
        $this->assertFalse($order->canBeViewedBy());
        $this->assertFalse($order->canBeEditedBy());
    }

    /**
     * Test owner-based access control
     */
    public function test_owner_can_access_own_records(): void
    {
        // Create workflow with RestrictedState
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, [
            'name' => 'restricted',
            'class_name' => RestrictedState::class,
            'is_initial' => true,
        ]);

        $owner = $this->createTestUser(['email' => 'owner@test.com', 'role' => 'user']);
        $otherUser = $this->createTestUser(['email' => 'other@test.com', 'role' => 'user']);

        $order = $this->createOrderInState(RestrictedState::class, [
            'user_id' => $owner->id,
        ]);

        // RestrictedState allows @owner for edit access
        $this->assertTrue($order->canBeEditedBy($owner));
        $this->assertFalse($order->canBeEditedBy($otherUser));
    }

    /**
     * Test role-based access control
     */
    public function test_role_based_access(): void
    {
        // Create workflow with RestrictedState
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, [
            'name' => 'restricted',
            'class_name' => RestrictedState::class,
            'is_initial' => true,
        ]);

        $manager = $this->createTestUser(['email' => 'manager@test.com', 'role' => 'manager']);
        $regularUser = $this->createTestUser(['email' => 'regular@test.com', 'role' => 'user']);

        $order = $this->createOrderInState(RestrictedState::class);

        // RestrictedState allows role:manager,admin for transition
        $this->assertTrue($order->canBeTransitionedBy($manager));
        $this->assertFalse($order->canBeTransitionedBy($regularUser));
    }

    /**
     * Test super admin bypasses all access checks
     */
    public function test_super_admin_bypasses_all_checks(): void
    {
        // Create workflow with RestrictedState
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, [
            'name' => 'restricted',
            'class_name' => RestrictedState::class,
            'is_initial' => true,
        ]);

        $superAdmin = $this->createTestUser(['email' => 'super@test.com', 'role' => 'super_admin']);

        $order = $this->createOrderInState(RestrictedState::class);

        // Super admin can do everything
        $this->assertTrue($order->canBeViewedBy($superAdmin));
        $this->assertTrue($order->canBeEditedBy($superAdmin));
        $this->assertTrue($order->canBeTransitionedBy($superAdmin));
    }

    // ===========================================
    // CREATE PERMISSION TESTS
    // ===========================================

    /**
     * Test create permission with Code-First rules
     */
    public function test_create_permission_with_code_first(): void
    {
        // Create workflow with RestrictedState as initial
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, [
            'name' => 'restricted',
            'class_name' => RestrictedState::class,
            'is_initial' => true,
        ]);

        $salesUser = $this->createTestUser(['email' => 'sales@test.com', 'role' => 'sales']);
        $regularUser = $this->createTestUser(['email' => 'regular@test.com', 'role' => 'user']);

        // RestrictedState allows role:sales,admin for create
        $this->assertTrue(Order::canBeCreatedBy($salesUser));
        $this->assertFalse(Order::canBeCreatedBy($regularUser));
    }

    /**
     * Test create permission defaults to @authenticated
     */
    public function test_create_permission_default_authenticated(): void
    {
        // Create workflow with PendingState (no HasAccessRules)
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
            'is_initial' => true,
        ]);

        $user = $this->createTestUser();

        // Default is @authenticated
        $this->assertTrue(Order::canBeCreatedBy($user));
        $this->assertFalse(Order::canBeCreatedBy());
    }

    // ===========================================
    // QUERY SCOPE TESTS
    // ===========================================

    /**
     * Test visibleTo scope filters records correctly
     */
    public function test_visible_to_scope(): void
    {
        $user = $this->createTestUser(['email' => 'viewer@test.com']);

        // Create multiple orders
        $order1 = $this->createOrderInState(PendingState::class);
        $order2 = $this->createOrderInState(ProcessingState::class);

        // Both should be visible (default @authenticated)
        $visibleOrders = Order::visibleTo($user)->get();

        $this->assertCount(2, $visibleOrders);
        $this->assertTrue($visibleOrders->contains('id', $order1->id));
        $this->assertTrue($visibleOrders->contains('id', $order2->id));
    }

    /**
     * Test editableBy scope filters records correctly
     */
    public function test_editable_by_scope(): void
    {
        $user = $this->createTestUser(['email' => 'editor@test.com']);

        $this->createOrderInState(PendingState::class);
        $this->createOrderInState(ProcessingState::class);

        // Both should be editable (default @authenticated)
        $editableOrders = Order::editableBy($user)->get();

        $this->assertCount(2, $editableOrders);
    }

    // ===========================================
    // ASSIGNMENT-BASED ACCESS TESTS
    // ===========================================

    /**
     * Test assigned users can access records
     */
    public function test_assigned_user_can_access(): void
    {
        // Create workflow with RestrictedState
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, [
            'name' => 'restricted',
            'class_name' => RestrictedState::class,
            'is_initial' => true,
        ]);

        $assignedUser = $this->createTestUser(['email' => 'assigned@test.com', 'role' => 'user']);
        $unassignedUser = $this->createTestUser(['email' => 'unassigned@test.com', 'role' => 'user']);

        $order = $this->createOrderInState(RestrictedState::class);

        // Assign user to the order
        $order->assignTo($assignedUser->id);

        // RestrictedState allows @assigned for edit
        $this->assertTrue($order->canBeEditedBy($assignedUser));
        $this->assertFalse($order->canBeEditedBy($unassignedUser));
    }

    // ===========================================
    // AUTHENTICATION CONTEXT TESTS
    // ===========================================

    /**
     * Test access control uses authenticated user when none specified
     */
    public function test_access_uses_authenticated_user(): void
    {
        $user = $this->createTestUser(['email' => 'logged@test.com']);
        Auth::login($user);

        $order = $this->createOrderInState(PendingState::class);

        // Should use the authenticated user
        $this->assertTrue($order->canBeViewedBy());
        $this->assertTrue($order->canBeEditedBy());

        Auth::logout();

        // Without authenticated user, should be denied
        $this->assertFalse($order->canBeViewedBy());
    }

    /**
     * Test access control with explicit user parameter
     */
    public function test_access_with_explicit_user(): void
    {
        $user1 = $this->createTestUser(['email' => 'user1@test.com']);
        $user2 = $this->createTestUser(['email' => 'user2@test.com']);

        Auth::login($user1);

        $order = $this->createOrderInState(PendingState::class);

        // Explicit user parameter should be used
        $this->assertTrue($order->canBeViewedBy($user2));

        Auth::logout();
    }

    // ===========================================
    // FILAMENT RESOURCE INTEGRATION PATTERNS
    // ===========================================

    /**
     * Test pattern for Filament Resource canView policy
     */
    public function test_filament_can_view_pattern(): void
    {
        $user = $this->createTestUser();
        $order = $this->createOrderInState(PendingState::class);

        // This pattern can be used in Filament Resource::canView()
        $canView = $order->canBeViewedBy($user);
        $this->assertTrue($canView);
    }

    /**
     * Test pattern for Filament Resource canCreate policy
     */
    public function test_filament_can_create_pattern(): void
    {
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
            'is_initial' => true,
        ]);

        $user = $this->createTestUser();

        // This pattern can be used in Filament Resource::canCreate()
        $canCreate = Order::canBeCreatedBy($user);
        $this->assertTrue($canCreate);
    }

    /**
     * Test pattern for Filament Resource canEdit policy
     */
    public function test_filament_can_edit_pattern(): void
    {
        $user = $this->createTestUser();
        $order = $this->createOrderInState(PendingState::class);

        // This pattern can be used in Filament Resource::canEdit()
        $canEdit = $order->canBeEditedBy($user);
        $this->assertTrue($canEdit);
    }

    /**
     * Test pattern for Filament table query scoping
     */
    public function test_filament_table_query_scoping(): void
    {
        $user = $this->createTestUser();

        $this->createOrderInState(PendingState::class);
        $this->createOrderInState(ProcessingState::class);
        $this->createOrderInState(PendingState::class);

        // This pattern can be used in Filament Resource::getEloquentQuery()
        $query = Order::visibleTo($user);

        $this->assertEquals(3, $query->count());
    }

    // ===========================================
    // ACCESS RULES RETRIEVAL TESTS
    // ===========================================

    /**
     * Test retrieving access rules for current state
     */
    public function test_get_state_access_rules(): void
    {
        $order = $this->createOrderInState(RestrictedState::class);

        // Get view rules
        $viewRules = $order->getStateAccessRules();
        $this->assertContains('@authenticated', $viewRules);

        // Get edit rules
        $editRules = $order->getStateAccessRules('edit');
        $this->assertContains('@owner', $editRules);
        $this->assertContains('@assigned', $editRules);

        // Get transition rules
        $transitionRules = $order->getStateAccessRules('transition');
        $this->assertContains('role:manager,admin', $transitionRules);
    }

    /**
     * Test state access control enabled check
     */
    public function test_state_access_enabled_check(): void
    {
        // Access control is enabled by default in tests
        $this->assertTrue(Order::isStateAccessEnabled());

        // Disable it
        config()->set('filament-flow.state_access.enabled', false);
        $this->assertFalse(Order::isStateAccessEnabled());

        // Re-enable for other tests
        config()->set('filament-flow.state_access.enabled', true);
    }

    /**
     * Test that disabled access control allows everything
     */
    public function test_disabled_access_allows_all(): void
    {
        config()->set('filament-flow.state_access.enabled', false);

        $order = $this->createOrderInState(RestrictedState::class);

        // Even without authentication, everything should be allowed
        $this->assertTrue($order->canBeViewedBy());
        $this->assertTrue($order->canBeEditedBy());
        $this->assertTrue($order->canBeTransitionedBy());

        // Re-enable for other tests
        config()->set('filament-flow.state_access.enabled', true);
    }
}
