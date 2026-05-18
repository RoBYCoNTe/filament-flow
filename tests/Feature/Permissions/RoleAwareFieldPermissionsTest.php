<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Permissions;

use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateField;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateFieldRole;
use RoBYCoNTe\FilamentFlow\Services\WorkflowFieldPermissionsService;
use RoBYCoNTe\FilamentFlow\Services\WorkflowStateAccessService;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Tests for role-aware field permissions, creation permissions via initial state,
 * table column visibility aggregation, and locked mutability.
 */
class RoleAwareFieldPermissionsTest extends TestCase
{
    private Workflow $workflow;

    private WorkflowState $draftState;

    private WorkflowState $reviewState;

    private WorkflowState $completedState;

    private User $admin;

    private User $editor;

    private User $viewer;

    private WorkflowFieldPermissionsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filament-flow.state_access.enabled', true);

        $this->service = new WorkflowFieldPermissionsService;
        $this->buildScenario();
    }

    private function buildScenario(): void
    {
        $this->workflow = $this->createTestWorkflow();

        $this->draftState = $this->createWorkflowState($this->workflow, [
            'name' => 'draft',
            'label' => 'Draft',
            'is_initial' => true,
            'sort_order' => 0,
        ]);

        $this->reviewState = $this->createWorkflowState($this->workflow, [
            'name' => 'review',
            'label' => 'Review',
            'sort_order' => 1,
        ]);

        $this->completedState = $this->createWorkflowState($this->workflow, [
            'name' => 'completed',
            'label' => 'Completed',
            'is_final' => true,
            'sort_order' => 2,
        ]);

        $this->admin = $this->createTestUser(['email' => 'admin@test.com', 'role' => 'admin']);
        $this->editor = $this->createTestUser(['email' => 'editor@test.com', 'role' => 'editor']);
        $this->viewer = $this->createTestUser(['email' => 'viewer@test.com', 'role' => 'viewer']);
    }

    private function createOrder(string $state = 'draft'): Order
    {
        return Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => $state,
            'user_id' => $this->editor->id,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  Role Overrides on field permissions
    // ──────────────────────────────────────────────────────────────

    public function test_base_field_permissions_without_role_overrides(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'customer_name',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);

        $order = $this->createOrder();
        $perms = $this->service->getFieldPermissions($order);

        $this->assertTrue($perms['customer_name']['visible']);
        $this->assertTrue($perms['customer_name']['readonly']);
        $this->assertFalse($perms['customer_name']['required']);
    }

    public function test_role_override_changes_visibility_for_matching_user(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'secret_field',
            'visibility' => 'hidden',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        // Admin can see it
        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => 'admin',
            'visibility' => 'visible',
            'mutability' => null,
            'is_required' => null,
        ]);

        $order = $this->createOrder();

        // Without user → base config (hidden)
        $perms = $this->service->getFieldPermissions($order);
        $this->assertFalse($perms['secret_field']['visible']);

        // With admin → override makes it visible
        $perms = $this->service->getFieldPermissions($order, $this->admin);
        $this->assertTrue($perms['secret_field']['visible']);

        // With editor → no matching override, stays hidden
        $perms = $this->service->getFieldPermissions($order, $this->editor);
        $this->assertFalse($perms['secret_field']['visible']);
    }

    public function test_role_override_changes_mutability(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'total_amount',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);

        // Admin can edit it
        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => 'admin',
            'visibility' => null,
            'mutability' => 'editable',
            'is_required' => null,
        ]);

        $order = $this->createOrder();

        $perms = $this->service->getFieldPermissions($order, $this->admin);
        $this->assertFalse($perms['total_amount']['readonly']);
        $this->assertFalse($perms['total_amount']['locked']);

        // Editor gets base readonly
        $perms = $this->service->getFieldPermissions($order, $this->editor);
        $this->assertTrue($perms['total_amount']['readonly']);
    }

    public function test_role_override_changes_required(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'notes',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        // For editors, notes are required
        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => 'editor',
            'visibility' => null,
            'mutability' => null,
            'is_required' => true,
        ]);

        $order = $this->createOrder();

        $perms = $this->service->getFieldPermissions($order, $this->editor);
        $this->assertTrue($perms['notes']['required']);

        $perms = $this->service->getFieldPermissions($order, $this->admin);
        $this->assertFalse($perms['notes']['required']);
    }

    // ──────────────────────────────────────────────────────────────
    //  Locked mutability
    // ──────────────────────────────────────────────────────────────

    public function test_locked_mutability_hides_field(): void
    {
        WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'internal_code',
            'visibility' => 'visible',
            'mutability' => 'locked',
            'is_required' => false,
        ]);

        $order = $this->createOrder();
        $perms = $this->service->getFieldPermissions($order);

        $this->assertTrue($perms['internal_code']['locked']);

        // getHiddenFields should include locked fields
        $hidden = $this->service->getHiddenFields($order);
        $this->assertContains('internal_code', $hidden);
    }

    public function test_role_override_can_unlock_locked_field(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'internal_code',
            'visibility' => 'visible',
            'mutability' => 'locked',
            'is_required' => false,
        ]);

        // Admin can see and edit it
        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => 'admin',
            'visibility' => null,
            'mutability' => 'editable',
            'is_required' => null,
        ]);

        $order = $this->createOrder();

        $perms = $this->service->getFieldPermissions($order, $this->admin);
        $this->assertFalse($perms['internal_code']['locked']);
        $this->assertFalse($perms['internal_code']['readonly']);

        // Editor still sees it locked
        $perms = $this->service->getFieldPermissions($order, $this->editor);
        $this->assertTrue($perms['internal_code']['locked']);
    }

    // ──────────────────────────────────────────────────────────────
    //  Creation field permissions (initial state)
    // ──────────────────────────────────────────────────────────────

    public function test_creation_field_permissions_use_initial_state(): void
    {
        WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'customer_name',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => true,
        ]);

        WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'tracking_number',
            'visibility' => 'hidden',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        $perms = $this->service->getCreationFieldPermissions(Order::class);

        $this->assertArrayHasKey('customer_name', $perms);
        $this->assertTrue($perms['customer_name']['visible']);
        $this->assertTrue($perms['customer_name']['required']);

        $this->assertArrayHasKey('tracking_number', $perms);
        $this->assertFalse($perms['tracking_number']['visible']);
    }

    public function test_creation_field_permissions_apply_role_overrides(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'priority',
            'visibility' => 'hidden',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => 'admin',
            'visibility' => 'visible',
            'mutability' => null,
            'is_required' => true,
        ]);

        // Admin sees priority during creation
        $perms = $this->service->getCreationFieldPermissions(Order::class, $this->admin);
        $this->assertTrue($perms['priority']['visible']);
        $this->assertTrue($perms['priority']['required']);

        // Editor doesn't
        $perms = $this->service->getCreationFieldPermissions(Order::class, $this->editor);
        $this->assertFalse($perms['priority']['visible']);
    }

    public function test_creation_field_permissions_empty_when_no_workflow(): void
    {
        $perms = $this->service->getCreationFieldPermissions('App\\Models\\NonExistent');
        $this->assertEmpty($perms);
    }

    // ──────────────────────────────────────────────────────────────
    //  Creation access (via initial state access rules)
    // ──────────────────────────────────────────────────────────────

    public function test_creation_access_via_initial_state_access_rules(): void
    {
        WorkflowStateAccessRule::create([
            'state_id' => $this->draftState->id,
            'access_type' => 'create',
            'rule' => 'role:admin,editor',
        ]);

        $accessService = app(WorkflowStateAccessService::class);

        $this->assertTrue($accessService->canCreate(Order::class, $this->admin));
        $this->assertTrue($accessService->canCreate(Order::class, $this->editor));
        $this->assertFalse($accessService->canCreate(Order::class, $this->viewer));
    }

    public function test_creation_access_defaults_to_authenticated_when_no_rules(): void
    {
        // No create rules on initial state → falls back to config defaults
        $accessService = app(WorkflowStateAccessService::class);

        // Default config allows @authenticated
        $this->assertTrue($accessService->canCreate(Order::class, $this->viewer));
    }

    // ──────────────────────────────────────────────────────────────
    //  Table column permissions (aggregated across states)
    // ──────────────────────────────────────────────────────────────

    public function test_table_column_visible_if_visible_in_any_state(): void
    {
        // tracking_number: hidden in draft, visible in review
        WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'tracking_number',
            'visibility' => 'hidden',
            'mutability' => 'editable',
        ]);

        WorkflowStateField::create([
            'state_id' => $this->reviewState->id,
            'field_name' => 'tracking_number',
            'visibility' => 'visible',
            'mutability' => 'readonly',
        ]);

        $perms = $this->service->getTableColumnPermissions(Order::class);

        $this->assertTrue($perms['tracking_number']['visible']);
    }

    public function test_table_column_hidden_if_hidden_in_all_states(): void
    {
        WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'internal_code',
            'visibility' => 'hidden',
            'mutability' => 'editable',
        ]);

        WorkflowStateField::create([
            'state_id' => $this->reviewState->id,
            'field_name' => 'internal_code',
            'visibility' => 'hidden',
            'mutability' => 'editable',
        ]);

        $perms = $this->service->getTableColumnPermissions(Order::class);

        $this->assertFalse($perms['internal_code']['visible']);
    }

    public function test_table_column_locked_in_all_states_is_hidden(): void
    {
        WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'secret',
            'visibility' => 'visible',
            'mutability' => 'locked',
        ]);

        WorkflowStateField::create([
            'state_id' => $this->reviewState->id,
            'field_name' => 'secret',
            'visibility' => 'visible',
            'mutability' => 'locked',
        ]);

        $perms = $this->service->getTableColumnPermissions(Order::class);

        $this->assertFalse($perms['secret']['visible']);
    }

    public function test_table_column_permissions_with_role_overrides(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'salary',
            'visibility' => 'hidden',
            'mutability' => 'editable',
        ]);

        // Admin can see salary
        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => 'admin',
            'visibility' => 'visible',
            'mutability' => null,
            'is_required' => null,
        ]);

        $perms = $this->service->getTableColumnPermissions(Order::class, $this->admin);
        $this->assertTrue($perms['salary']['visible']);

        $perms = $this->service->getTableColumnPermissions(Order::class, $this->editor);
        $this->assertFalse($perms['salary']['visible']);
    }

    public function test_table_column_permissions_empty_when_no_workflow(): void
    {
        $perms = $this->service->getTableColumnPermissions('App\\Models\\NonExistent');
        $this->assertEmpty($perms);
    }

    // ──────────────────────────────────────────────────────────────
    //  getReadonlyFields and getHiddenFields with user
    // ──────────────────────────────────────────────────────────────

    public function test_get_readonly_fields_with_user(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'total_amount',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        // Viewer sees it as readonly
        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => 'viewer',
            'visibility' => null,
            'mutability' => 'readonly',
            'is_required' => null,
        ]);

        $order = $this->createOrder();

        $readonly = $this->service->getReadonlyFields($order, $this->viewer);
        $this->assertContains('total_amount', $readonly);

        $readonly = $this->service->getReadonlyFields($order, $this->editor);
        $this->assertNotContains('total_amount', $readonly);
    }

    public function test_get_hidden_fields_with_user(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'confidential',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        // Viewer can't see it
        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => 'viewer',
            'visibility' => 'hidden',
            'mutability' => null,
            'is_required' => null,
        ]);

        $order = $this->createOrder();

        $hidden = $this->service->getHiddenFields($order, $this->viewer);
        $this->assertContains('confidential', $hidden);

        $hidden = $this->service->getHiddenFields($order, $this->admin);
        $this->assertNotContains('confidential', $hidden);
    }

    // ──────────────────────────────────────────────────────────────
    //  Multiple role overrides (last wins)
    // ──────────────────────────────────────────────────────────────

    public function test_multiple_role_overrides_last_matching_wins(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'notes',
            'visibility' => 'hidden',
            'mutability' => 'locked',
            'is_required' => false,
        ]);

        // First override: editor → visible, readonly
        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => 'editor',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);

        $order = $this->createOrder();
        $perms = $this->service->getFieldPermissions($order, $this->editor);

        $this->assertTrue($perms['notes']['visible']);
        $this->assertTrue($perms['notes']['readonly']);
        $this->assertFalse($perms['notes']['locked']);
    }

    // ──────────────────────────────────────────────────────────────
    //  Edge cases
    // ──────────────────────────────────────────────────────────────

    public function test_no_field_permissions_returns_empty(): void
    {
        $order = $this->createOrder();
        $perms = $this->service->getFieldPermissions($order);
        $this->assertEmpty($perms);
    }

    public function test_no_initial_state_returns_empty_creation_permissions(): void
    {
        // Remove initial flag from draft
        $this->draftState->update(['is_initial' => false]);

        WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'customer_name',
            'visibility' => 'visible',
            'mutability' => 'editable',
        ]);

        $perms = $this->service->getCreationFieldPermissions(Order::class);
        $this->assertEmpty($perms);
    }

    public function test_field_permissions_for_nonexistent_state_returns_empty(): void
    {
        $order = $this->createOrder('nonexistent_state');
        $perms = $this->service->getFieldPermissions($order);
        $this->assertEmpty($perms);
    }
}
