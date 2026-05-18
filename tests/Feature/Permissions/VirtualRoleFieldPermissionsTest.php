<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Permissions;

use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateField;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateFieldRole;
use RoBYCoNTe\FilamentFlow\Services\WorkflowFieldPermissionsService;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Tests for virtual role conditions (@owner, @assigned, @assigned:type)
 * in field permission overrides.
 */
class VirtualRoleFieldPermissionsTest extends TestCase
{
    private Workflow $workflow;

    private WorkflowState $draftState;

    private WorkflowState $reviewState;

    private User $owner;

    private User $otherUser;

    private User $admin;

    private WorkflowFieldPermissionsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filament-flow.state_access.enabled', true);
        config()->set('filament-flow.state_access.owner_field', 'user_id');

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

        $this->owner = $this->createTestUser(['email' => 'owner@test.com', 'role' => 'editor']);
        $this->otherUser = $this->createTestUser(['email' => 'other@test.com', 'role' => 'editor']);
        $this->admin = $this->createTestUser(['email' => 'admin@test.com', 'role' => 'admin']);
    }

    private function createOrder(string $state = 'draft', ?User $owner = null): Order
    {
        return Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => $state,
            'user_id' => ($owner ?? $this->owner)->id,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  @owner virtual role
    // ──────────────────────────────────────────────────────────────

    public function test_owner_override_makes_field_editable_for_owner(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'notes',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);

        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => '@owner',
            'visibility' => null,
            'mutability' => 'editable',
            'is_required' => null,
        ]);

        $order = $this->createOrder();

        // Owner → editable
        $perms = $this->service->getFieldPermissions($order, $this->owner);
        $this->assertFalse($perms['notes']['readonly']);

        // Non-owner (same role) → base readonly
        $perms = $this->service->getFieldPermissions($order, $this->otherUser);
        $this->assertTrue($perms['notes']['readonly']);
    }

    public function test_owner_override_changes_visibility(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'internal_notes',
            'visibility' => 'hidden',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => '@owner',
            'visibility' => 'visible',
            'mutability' => null,
            'is_required' => null,
        ]);

        $order = $this->createOrder();

        $perms = $this->service->getFieldPermissions($order, $this->owner);
        $this->assertTrue($perms['internal_notes']['visible']);

        $perms = $this->service->getFieldPermissions($order, $this->otherUser);
        $this->assertFalse($perms['internal_notes']['visible']);
    }

    public function test_owner_override_combined_with_static_role(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'total_amount',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);

        // Admin can always edit
        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => 'admin',
            'visibility' => null,
            'mutability' => 'editable',
            'is_required' => null,
        ]);

        // Owner can also edit
        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => '@owner',
            'visibility' => null,
            'mutability' => 'editable',
            'is_required' => null,
        ]);

        $order = $this->createOrder();

        // Owner (editor role) → editable via @owner
        $perms = $this->service->getFieldPermissions($order, $this->owner);
        $this->assertFalse($perms['total_amount']['readonly']);

        // Admin (non-owner) → editable via admin role
        $perms = $this->service->getFieldPermissions($order, $this->admin);
        $this->assertFalse($perms['total_amount']['readonly']);

        // Other editor (non-owner) → base readonly
        $perms = $this->service->getFieldPermissions($order, $this->otherUser);
        $this->assertTrue($perms['total_amount']['readonly']);
    }

    public function test_owner_not_matched_when_owner_field_is_null(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'notes',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);

        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => '@owner',
            'visibility' => null,
            'mutability' => 'editable',
            'is_required' => null,
        ]);

        // Order without user_id
        $order = Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'draft',
            'user_id' => null,
        ]);

        $perms = $this->service->getFieldPermissions($order, $this->owner);
        $this->assertTrue($perms['notes']['readonly']);
    }

    // ──────────────────────────────────────────────────────────────
    //  @owner in creation context
    // ──────────────────────────────────────────────────────────────

    public function test_owner_override_applies_during_creation(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'priority',
            'visibility' => 'hidden',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        // Owner can see priority field
        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => '@owner',
            'visibility' => 'visible',
            'mutability' => null,
            'is_required' => true,
        ]);

        // During creation, the user is always the owner
        $perms = $this->service->getCreationFieldPermissions(Order::class, $this->owner);
        $this->assertTrue($perms['priority']['visible']);
        $this->assertTrue($perms['priority']['required']);

        // Any user is the owner during creation
        $perms = $this->service->getCreationFieldPermissions(Order::class, $this->otherUser);
        $this->assertTrue($perms['priority']['visible']);
    }

    // ──────────────────────────────────────────────────────────────
    //  @assigned virtual role
    // ──────────────────────────────────────────────────────────────

    public function test_assigned_override_for_assigned_user(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->reviewState->id,
            'field_name' => 'notes',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);

        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => '@assigned',
            'visibility' => null,
            'mutability' => 'editable',
            'is_required' => null,
        ]);

        $order = $this->createOrder('review');
        $order->assignTo($this->otherUser, 'primary', $this->admin);

        // Assigned user → editable
        $perms = $this->service->getFieldPermissions($order, $this->otherUser);
        $this->assertFalse($perms['notes']['readonly']);

        // Non-assigned user → base readonly
        $perms = $this->service->getFieldPermissions($order, $this->admin);
        $this->assertTrue($perms['notes']['readonly']);
    }

    public function test_assigned_type_override_for_specific_assignment(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->reviewState->id,
            'field_name' => 'approval_status',
            'visibility' => 'hidden',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        // Only primary assignees can see the approval field
        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => '@assigned:primary',
            'visibility' => 'visible',
            'mutability' => null,
            'is_required' => null,
        ]);

        $order = $this->createOrder('review');
        $order->assignTo($this->otherUser, 'primary', $this->admin);
        $order->assignTo($this->admin, 'secondary', $this->admin);

        // Primary assignee → visible
        $perms = $this->service->getFieldPermissions($order, $this->otherUser);
        $this->assertTrue($perms['approval_status']['visible']);

        // Secondary assignee → base hidden (no @assigned:primary match)
        $perms = $this->service->getFieldPermissions($order, $this->admin);
        $this->assertFalse($perms['approval_status']['visible']);
    }

    public function test_assigned_generic_matches_any_assignment_type(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->reviewState->id,
            'field_name' => 'review_notes',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);

        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => '@assigned',
            'visibility' => null,
            'mutability' => 'editable',
            'is_required' => null,
        ]);

        $order = $this->createOrder('review');
        $order->assignTo($this->otherUser, 'secondary', $this->admin);

        // Secondary assignee matches @assigned (any type)
        $perms = $this->service->getFieldPermissions($order, $this->otherUser);
        $this->assertFalse($perms['review_notes']['readonly']);
    }

    public function test_assigned_not_matched_when_model_has_no_assignments(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->reviewState->id,
            'field_name' => 'notes',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);

        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => '@assigned',
            'visibility' => null,
            'mutability' => 'editable',
            'is_required' => null,
        ]);

        $order = $this->createOrder('review');
        // No assignments made

        $perms = $this->service->getFieldPermissions($order, $this->otherUser);
        $this->assertTrue($perms['notes']['readonly']);
    }

    // ──────────────────────────────────────────────────────────────
    //  Combined: @owner + @assigned + static roles
    // ──────────────────────────────────────────────────────────────

    public function test_owner_and_assigned_both_resolve_for_same_user(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->reviewState->id,
            'field_name' => 'notes',
            'visibility' => 'hidden',
            'mutability' => 'locked',
            'is_required' => false,
        ]);

        // @owner makes it visible
        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => '@owner',
            'visibility' => 'visible',
            'mutability' => null,
            'is_required' => null,
        ]);

        // @assigned makes it editable
        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => '@assigned',
            'visibility' => null,
            'mutability' => 'editable',
            'is_required' => null,
        ]);

        $order = $this->createOrder('review');
        $order->assignTo($this->owner, 'primary', $this->admin);

        // Owner who is also assigned → both overrides apply
        $perms = $this->service->getFieldPermissions($order, $this->owner);
        $this->assertTrue($perms['notes']['visible']);
        $this->assertFalse($perms['notes']['locked']);
        $this->assertFalse($perms['notes']['readonly']);
    }

    public function test_static_role_and_virtual_role_coexist(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'customer_name',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        // Editor role makes it required
        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => 'editor',
            'visibility' => null,
            'mutability' => null,
            'is_required' => true,
        ]);

        // Owner makes it readonly (owner-specific restriction)
        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => '@owner',
            'visibility' => null,
            'mutability' => 'readonly',
            'is_required' => null,
        ]);

        $order = $this->createOrder();

        // Owner (who is also editor): editor sets required, @owner sets readonly
        $perms = $this->service->getFieldPermissions($order, $this->owner);
        $this->assertTrue($perms['customer_name']['required']);
        $this->assertTrue($perms['customer_name']['readonly']);

        // Other editor (not owner): only editor override applies
        $perms = $this->service->getFieldPermissions($order, $this->otherUser);
        $this->assertTrue($perms['customer_name']['required']);
        $this->assertFalse($perms['customer_name']['readonly']);
    }

    // ──────────────────────────────────────────────────────────────
    //  Table column permissions (virtual roles skipped without record)
    // ──────────────────────────────────────────────────────────────

    public function test_table_column_permissions_ignore_virtual_roles(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'secret_field',
            'visibility' => 'hidden',
            'mutability' => 'editable',
        ]);

        // @owner override — cannot be evaluated without a specific record
        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => '@owner',
            'visibility' => 'visible',
            'mutability' => null,
            'is_required' => null,
        ]);

        // Table column permissions have no record context → @owner not matched
        $perms = $this->service->getTableColumnPermissions(Order::class, $this->owner);
        $this->assertFalse($perms['secret_field']['visible']);
    }

    // ──────────────────────────────────────────────────────────────
    //  getReadonlyFields / getHiddenFields with virtual roles
    // ──────────────────────────────────────────────────────────────

    public function test_get_readonly_fields_respects_owner_override(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'total_amount',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);

        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => '@owner',
            'visibility' => null,
            'mutability' => 'editable',
            'is_required' => null,
        ]);

        $order = $this->createOrder();

        $readonly = $this->service->getReadonlyFields($order, $this->owner);
        $this->assertNotContains('total_amount', $readonly);

        $readonly = $this->service->getReadonlyFields($order, $this->otherUser);
        $this->assertContains('total_amount', $readonly);
    }

    public function test_get_hidden_fields_respects_assigned_override(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->reviewState->id,
            'field_name' => 'confidential',
            'visibility' => 'hidden',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => '@assigned',
            'visibility' => 'visible',
            'mutability' => null,
            'is_required' => null,
        ]);

        $order = $this->createOrder('review');
        $order->assignTo($this->otherUser, 'primary', $this->admin);

        $hidden = $this->service->getHiddenFields($order, $this->otherUser);
        $this->assertNotContains('confidential', $hidden);

        $hidden = $this->service->getHiddenFields($order, $this->admin);
        $this->assertContains('confidential', $hidden);
    }
}
