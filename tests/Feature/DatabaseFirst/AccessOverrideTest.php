<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Services\WorkflowStateAccessService;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Test per-assignment access overrides.
 *
 * Verifies that assignment-level override columns (override_view, override_edit,
 * override_transition) correctly bypass state-based access rules.
 */
class AccessOverrideTest extends TestCase
{
    protected WorkflowStateAccessService $accessService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accessService = app(WorkflowStateAccessService::class);
    }

    public function test_assign_with_overrides_stores_override_columns(): void
    {
        $user = $this->createTestUser(['name' => 'Test User', 'email' => 'override1@test.com']);
        $order = Order::create(['order_number' => 'OVR-001', 'customer_name' => 'Test', 'total_amount' => 100]);

        $assignment = $order->assignWithOverrides($user, [
            'view' => true,
            'edit' => true,
            'transition' => null,
        ]);

        $this->assertTrue($assignment->override_view);
        $this->assertTrue($assignment->override_edit);
        $this->assertNull($assignment->override_transition);
        $this->assertTrue($assignment->hasAccessOverride());
        $this->assertTrue($assignment->hasOverrideFor('view'));
        $this->assertTrue($assignment->hasOverrideFor('edit'));
        $this->assertFalse($assignment->hasOverrideFor('transition'));
    }

    public function test_assign_without_overrides_has_null_columns(): void
    {
        $user = $this->createTestUser(['name' => 'Test User', 'email' => 'override2@test.com']);
        $order = Order::create(['order_number' => 'OVR-002', 'customer_name' => 'Test', 'total_amount' => 100]);

        $assignment = $order->assignTo($user);

        $this->assertNull($assignment->override_view);
        $this->assertNull($assignment->override_edit);
        $this->assertNull($assignment->override_transition);
        $this->assertFalse($assignment->hasAccessOverride());
    }

    public function test_update_access_overrides_on_existing_assignment(): void
    {
        $user = $this->createTestUser(['name' => 'Test User', 'email' => 'override3@test.com']);
        $order = Order::create(['order_number' => 'OVR-003', 'customer_name' => 'Test', 'total_amount' => 100]);

        $order->assignTo($user);
        $this->assertFalse($order->hasAccessOverride($user, 'view'));

        $order->updateAccessOverrides($user, ['view' => true, 'edit' => true]);

        $this->assertTrue($order->hasAccessOverride($user, 'view'));
        $this->assertTrue($order->hasAccessOverride($user, 'edit'));
        $this->assertFalse($order->hasAccessOverride($user, 'transition'));
    }

    public function test_override_grants_view_access_bypassing_state_rules(): void
    {
        $this->createOrderWorkflowWithRestrictedState();

        $user = $this->createTestUser(['name' => 'Restricted User', 'email' => 'override4@test.com']);
        $order = Order::create(['order_number' => 'OVR-004', 'customer_name' => 'Test', 'total_amount' => 100, 'state' => 'restricted']);

        // Without override, user cannot view (state only allows role:admin)
        $this->assertFalse($this->accessService->canView($order, $user));

        // With override, user can view
        $order->assignWithOverrides($user, ['view' => true]);
        $this->assertTrue($this->accessService->canView($order, $user));
    }

    public function test_override_grants_edit_access_bypassing_state_rules(): void
    {
        $this->createOrderWorkflowWithRestrictedState();

        $user = $this->createTestUser(['name' => 'Restricted User', 'email' => 'override5@test.com']);
        $order = Order::create(['order_number' => 'OVR-005', 'customer_name' => 'Test', 'total_amount' => 100, 'state' => 'restricted']);

        // Without override, user cannot edit
        $this->assertFalse($this->accessService->canEdit($order, $user));

        // With view override only, still cannot edit
        $order->assignWithOverrides($user, ['view' => true]);
        $this->assertFalse($this->accessService->canEdit($order, $user));

        // With edit override, can edit
        $order->updateAccessOverrides($user, ['view' => true, 'edit' => true]);
        $this->assertTrue($this->accessService->canEdit($order, $user));
    }

    public function test_override_grants_transition_access_bypassing_state_rules(): void
    {
        $this->createOrderWorkflowWithRestrictedState();

        $user = $this->createTestUser(['name' => 'Restricted User', 'email' => 'override6@test.com']);
        $order = Order::create(['order_number' => 'OVR-006', 'customer_name' => 'Test', 'total_amount' => 100, 'state' => 'restricted']);

        $this->assertFalse($this->accessService->canTransition($order, $user));

        $order->assignWithOverrides($user, ['transition' => true]);
        $this->assertTrue($this->accessService->canTransition($order, $user));
    }

    public function test_normal_assignment_without_override_follows_state_rules(): void
    {
        $this->createOrderWorkflowWithRestrictedState();

        $user = $this->createTestUser(['name' => 'Normal User', 'email' => 'override7@test.com']);
        $order = Order::create(['order_number' => 'OVR-007', 'customer_name' => 'Test', 'total_amount' => 100, 'state' => 'restricted']);

        // Normal assignment (no override) — state rules still apply
        $order->assignTo($user);
        $this->assertFalse($this->accessService->canView($order, $user));
        $this->assertFalse($this->accessService->canEdit($order, $user));
    }

    public function test_removing_override_revokes_access(): void
    {
        $this->createOrderWorkflowWithRestrictedState();

        $user = $this->createTestUser(['name' => 'Revoke User', 'email' => 'override8@test.com']);
        $order = Order::create(['order_number' => 'OVR-008', 'customer_name' => 'Test', 'total_amount' => 100, 'state' => 'restricted']);

        $order->assignWithOverrides($user, ['view' => true, 'edit' => true]);
        $this->assertTrue($this->accessService->canView($order, $user));

        // Remove overrides
        $order->updateAccessOverrides($user, ['view' => null, 'edit' => null]);
        $this->assertFalse($this->accessService->canView($order, $user));
    }

    public function test_scope_accessible_includes_override_records(): void
    {
        $this->createOrderWorkflowWithRestrictedState();

        $user = $this->createTestUser(['name' => 'Scope User', 'email' => 'override9@test.com']);
        $order = Order::create(['order_number' => 'OVR-009', 'customer_name' => 'Test', 'total_amount' => 100, 'state' => 'restricted']);

        // Without override, not visible in scope
        $visible = Order::query()->tap(fn ($q) => $this->accessService->scopeAccessible($q, $user))->get();
        $this->assertFalse($visible->contains('id', $order->id));

        // With override, visible in scope
        $order->assignWithOverrides($user, ['view' => true]);
        $visible = Order::query()->tap(fn ($q) => $this->accessService->scopeAccessible($q, $user))->get();
        $this->assertTrue($visible->contains('id', $order->id));
    }

    public function test_assign_with_overrides_updates_existing_assignment(): void
    {
        $user = $this->createTestUser(['name' => 'Update User', 'email' => 'override10@test.com']);
        $order = Order::create(['order_number' => 'OVR-010', 'customer_name' => 'Test', 'total_amount' => 100]);

        // First assignment without overrides
        $order->assignTo($user);
        $this->assertEquals(1, $order->assignments()->count());

        // Update with overrides — should not create duplicate
        $order->assignWithOverrides($user, ['view' => true]);
        $this->assertEquals(1, $order->assignments()->count());
        $this->assertTrue($order->hasAccessOverride($user, 'view'));
    }

    public function test_override_view_does_not_grant_edit_or_transition(): void
    {
        $this->createOrderWorkflowWithRestrictedState();

        $user = $this->createTestUser(['name' => 'View Only', 'email' => 'override11@test.com']);
        $order = Order::create(['order_number' => 'OVR-011', 'customer_name' => 'Test', 'total_amount' => 100, 'state' => 'restricted']);

        $order->assignWithOverrides($user, ['view' => true]);

        $this->assertTrue($this->accessService->canView($order, $user));
        $this->assertFalse($this->accessService->canEdit($order, $user));
        $this->assertFalse($this->accessService->canTransition($order, $user));
    }

    public function test_multiple_users_with_different_overrides(): void
    {
        $this->createOrderWorkflowWithRestrictedState();

        $viewer = $this->createTestUser(['name' => 'Viewer', 'email' => 'override12@test.com']);
        $editor = $this->createTestUser(['name' => 'Editor', 'email' => 'override13@test.com']);

        $order = Order::create(['order_number' => 'OVR-012', 'customer_name' => 'Test', 'total_amount' => 100, 'state' => 'restricted']);

        $order->assignWithOverrides($viewer, ['view' => true]);
        $order->assignWithOverrides($editor, ['view' => true, 'edit' => true]);

        $this->assertTrue($this->accessService->canView($order, $viewer));
        $this->assertFalse($this->accessService->canEdit($order, $viewer));

        $this->assertTrue($this->accessService->canView($order, $editor));
        $this->assertTrue($this->accessService->canEdit($order, $editor));
    }

    public function test_toggle_override_on_and_off(): void
    {
        $this->createOrderWorkflowWithRestrictedState();

        $user = $this->createTestUser(['name' => 'Toggle User', 'email' => 'override14@test.com']);
        $order = Order::create(['order_number' => 'OVR-013', 'customer_name' => 'Test', 'total_amount' => 100, 'state' => 'restricted']);

        $order->assignWithOverrides($user, ['view' => true]);
        $this->assertTrue($this->accessService->canView($order, $user));

        // Toggle off
        $order->updateAccessOverrides($user, ['view' => null]);
        $this->assertFalse($this->accessService->canView($order, $user));

        // Toggle back on
        $order->updateAccessOverrides($user, ['view' => true]);
        $this->assertTrue($this->accessService->canView($order, $user));
    }

    public function test_override_persists_across_fresh_model_load(): void
    {
        $user = $this->createTestUser(['name' => 'Persist User', 'email' => 'override15@test.com']);
        $order = Order::create(['order_number' => 'OVR-014', 'customer_name' => 'Test', 'total_amount' => 100]);

        $order->assignWithOverrides($user, ['view' => true, 'edit' => true]);

        // Reload from database
        $freshOrder = Order::find($order->id);
        $assignment = $freshOrder->assignments()->where('user_id', $user->id)->first();

        $this->assertTrue($assignment->override_view);
        $this->assertTrue($assignment->override_edit);
        $this->assertNull($assignment->override_transition);
    }

    public function test_scope_accessible_with_mixed_normal_and_override_assignments(): void
    {
        $this->createOrderWorkflowWithRestrictedState();

        $user = $this->createTestUser(['name' => 'Mixed User', 'email' => 'override16@test.com']);

        // Order with normal assignment (no override) — restricted state denies access
        $order1 = Order::create(['order_number' => 'OVR-015', 'customer_name' => 'Test1', 'total_amount' => 100, 'state' => 'restricted']);
        $order1->assignTo($user);

        // Order with override — should be visible
        $order2 = Order::create(['order_number' => 'OVR-016', 'customer_name' => 'Test2', 'total_amount' => 100, 'state' => 'restricted']);
        $order2->assignWithOverrides($user, ['view' => true]);

        $visible = Order::query()
            ->tap(fn ($q) => $this->accessService->scopeAccessible($q, $user))
            ->pluck('id');

        $this->assertFalse($visible->contains($order1->id));
        $this->assertTrue($visible->contains($order2->id));
    }

    public function test_super_admin_bypasses_everything_regardless_of_overrides(): void
    {
        $this->createOrderWorkflowWithRestrictedState();

        $superAdmin = $this->createTestUser([
            'name' => 'Super Admin',
            'email' => 'override17@test.com',
            'role' => 'super_admin',
        ]);

        $order = Order::create(['order_number' => 'OVR-017', 'customer_name' => 'Test', 'total_amount' => 100, 'state' => 'restricted']);

        // No assignment, no override — super admin still has access
        $this->assertTrue($this->accessService->canView($order, $superAdmin));
        $this->assertTrue($this->accessService->canEdit($order, $superAdmin));
        $this->assertTrue($this->accessService->canTransition($order, $superAdmin));
    }

    /**
     * Create a workflow with a "restricted" state that only allows role:admin access.
     */
    protected function createOrderWorkflowWithRestrictedState(): void
    {
        $workflow = $this->createTestWorkflow([
            'name' => 'Order Override Test',
            'model_type' => Order::class,
            'state_column' => 'state',
        ]);

        $state = $this->createWorkflowState($workflow, [
            'name' => 'restricted',
            'label' => 'Restricted',
            'is_initial' => true,
            'sort_order' => 10,
        ]);

        // Only role:admin can access this state
        foreach (['view', 'edit', 'transition'] as $accessType) {
            WorkflowStateAccessRule::create([
                'state_id' => $state->id,
                'access_type' => $accessType,
                'rule' => 'role:admin',
                'priority' => 0,
                'is_active' => true,
            ]);
        }
    }
}
