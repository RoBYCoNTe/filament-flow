<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Services;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateField;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateFieldRole;
use RoBYCoNTe\FilamentFlow\Services\WorkflowFieldPermissionsService;
use RoBYCoNTe\FilamentFlow\Support\DefaultRoleResolver;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Phase 5 tests for WorkflowFieldPermissionsService.
 *
 * Covers: getFieldPermissions, getCreationFieldPermissions,
 * getTableColumnPermissions, getReadonlyFields, getHiddenFields,
 * role overrides, virtual roles (@owner, @assigned), and OR-logic
 * for table column aggregation.
 */
class WorkflowFieldPermissionsServiceTest extends TestCase
{
    private Workflow $workflow;

    private WorkflowState $draftState;

    private WorkflowState $reviewState;

    private User $admin;

    private User $editor;

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

        $this->admin = $this->createTestUser(['email' => 'admin@test.com', 'role' => 'admin']);
        $this->editor = $this->createTestUser(['email' => 'editor@test.com', 'role' => 'editor']);
    }

    private function createOrder(string $state = 'draft', ?User $owner = null): Order
    {
        return Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'customer_name' => 'Test Customer',
            'total_amount' => 100,
            'state' => $state,
            'user_id' => ($owner ?? $this->editor)->id,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  Basic field permissions structure
    // ──────────────────────────────────────────────────────────────

    public function test_get_field_permissions_returns_config(): void
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
            'field_name' => 'notes',
            'visibility' => 'hidden',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);

        $order = $this->createOrder();
        $perms = $this->service->getFieldPermissions($order);

        // customer_name: visible, editable, required
        $this->assertArrayHasKey('customer_name', $perms);
        $this->assertTrue($perms['customer_name']['visible']);
        $this->assertFalse($perms['customer_name']['readonly']);
        $this->assertFalse($perms['customer_name']['locked']);
        $this->assertTrue($perms['customer_name']['required']);

        // notes: hidden, readonly, not required
        $this->assertArrayHasKey('notes', $perms);
        $this->assertFalse($perms['notes']['visible']);
        $this->assertTrue($perms['notes']['readonly']);
        $this->assertFalse($perms['notes']['locked']);
        $this->assertFalse($perms['notes']['required']);
    }

    public function test_field_permissions_empty_without_workflow(): void
    {
        // Create an order whose model class has no workflow
        // We remove the workflow so findForModel returns null
        $this->workflow->update(['is_active' => false]);

        $order = $this->createOrder();
        $perms = $this->service->getFieldPermissions($order);

        $this->assertEmpty($perms);
    }

    public function test_field_permissions_empty_without_state(): void
    {
        WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'customer_name',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        // Record state does not match any WorkflowState
        $order = $this->createOrder('nonexistent_state');
        $perms = $this->service->getFieldPermissions($order);

        $this->assertEmpty($perms);
    }

    // ──────────────────────────────────────────────────────────────
    //  Role overrides
    // ──────────────────────────────────────────────────────────────

    public function test_role_override_applied(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'secret_field',
            'visibility' => 'hidden',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        // Admin override: make visible
        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => 'admin',
            'visibility' => 'visible',
            'mutability' => null,
            'is_required' => null,
        ]);

        $order = $this->createOrder();

        // Admin sees it
        $perms = $this->service->getFieldPermissions($order, $this->admin);
        $this->assertTrue($perms['secret_field']['visible']);

        // Editor does not
        $perms = $this->service->getFieldPermissions($order, $this->editor);
        $this->assertFalse($perms['secret_field']['visible']);
    }

    public function test_multiple_role_overrides_last_wins(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'notes',
            'visibility' => 'hidden',
            'mutability' => 'locked',
            'is_required' => false,
        ]);

        // First override: editor → visible, readonly, not required
        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => 'editor',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);

        // Second override: admin → visible, editable, required
        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => 'admin',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => true,
        ]);

        $order = $this->createOrder();

        // Test with admin user - admin override: visible, editable, required
        $perms = $this->service->getFieldPermissions($order, $this->admin);
        $this->assertTrue($perms['notes']['visible']);
        $this->assertFalse($perms['notes']['readonly']);
        $this->assertFalse($perms['notes']['locked']);
        $this->assertTrue($perms['notes']['required']);

        // Test with editor user - editor override: visible, readonly, not required
        $perms = $this->service->getFieldPermissions($order, $this->editor);
        $this->assertTrue($perms['notes']['visible']);
        $this->assertTrue($perms['notes']['readonly']);
        $this->assertFalse($perms['notes']['locked']);
        $this->assertFalse($perms['notes']['required']);
    }

    // ──────────────────────────────────────────────────────────────
    //  Virtual roles: @owner
    // ──────────────────────────────────────────────────────────────

    public function test_owner_virtual_role(): void
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

        // Order owned by editor
        $order = $this->createOrder('draft', $this->editor);

        // Owner (editor) sees the field
        $perms = $this->service->getFieldPermissions($order, $this->editor);
        $this->assertTrue($perms['internal_notes']['visible']);

        // Non-owner (admin) does not
        $perms = $this->service->getFieldPermissions($order, $this->admin);
        $this->assertFalse($perms['internal_notes']['visible']);
    }

    // ──────────────────────────────────────────────────────────────
    //  Virtual roles: @assigned
    // ──────────────────────────────────────────────────────────────

    public function test_assigned_virtual_role(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->reviewState->id,
            'field_name' => 'review_notes',
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
        $order->assignTo($this->admin, 'primary', $this->editor);

        // Assigned user (admin) sees the field
        $perms = $this->service->getFieldPermissions($order, $this->admin);
        $this->assertTrue($perms['review_notes']['visible']);

        // Non-assigned user (editor, who is the owner but not assigned) does not
        // (editor is owner but @owner override is not configured here)
        $perms = $this->service->getFieldPermissions($order, $this->editor);
        $this->assertFalse($perms['review_notes']['visible']);
    }

    // ──────────────────────────────────────────────────────────────
    //  Creation permissions
    // ──────────────────────────────────────────────────────────────

    public function test_creation_permissions_use_initial_state(): void
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

        // Field on a non-initial state should NOT appear in creation permissions
        WorkflowStateField::create([
            'state_id' => $this->reviewState->id,
            'field_name' => 'review_decision',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => true,
        ]);

        $perms = $this->service->getCreationFieldPermissions(Order::class);

        $this->assertArrayHasKey('customer_name', $perms);
        $this->assertTrue($perms['customer_name']['visible']);
        $this->assertTrue($perms['customer_name']['required']);

        $this->assertArrayHasKey('tracking_number', $perms);
        $this->assertFalse($perms['tracking_number']['visible']);

        // review_decision belongs to review state, not initial
        $this->assertArrayNotHasKey('review_decision', $perms);
    }

    public function test_creation_owner_role_always_applied(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'priority',
            'visibility' => 'hidden',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        // @owner override makes the field visible
        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => '@owner',
            'visibility' => 'visible',
            'mutability' => null,
            'is_required' => true,
        ]);

        // During creation, @owner is always applied regardless of who the user is
        $perms = $this->service->getCreationFieldPermissions(Order::class, $this->admin);
        $this->assertTrue($perms['priority']['visible']);
        $this->assertTrue($perms['priority']['required']);

        $perms = $this->service->getCreationFieldPermissions(Order::class, $this->editor);
        $this->assertTrue($perms['priority']['visible']);
        $this->assertTrue($perms['priority']['required']);
    }

    // ──────────────────────────────────────────────────────────────
    //  Table column permissions (OR-logic across states)
    // ──────────────────────────────────────────────────────────────

    public function test_table_permissions_or_logic(): void
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

        // Visible in at least one state → visible in table
        $this->assertTrue($perms['tracking_number']['visible']);
    }

    public function test_table_locked_overrides_visible(): void
    {
        // state1: visible + editable → effectiveVisible = true
        WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'internal_code',
            'visibility' => 'visible',
            'mutability' => 'editable',
        ]);

        // state2: visible + locked → effectiveVisible = false
        WorkflowStateField::create([
            'state_id' => $this->reviewState->id,
            'field_name' => 'internal_code',
            'visibility' => 'visible',
            'mutability' => 'locked',
        ]);

        $perms = $this->service->getTableColumnPermissions(Order::class);

        // OR logic: effectiveVisible is true in draft (visible && !locked),
        // even though it's false in review (visible && locked).
        // So the overall result is visible=true.
        $this->assertTrue($perms['internal_code']['visible']);
    }

    // ──────────────────────────────────────────────────────────────
    //  Helper methods: getReadonlyFields / getHiddenFields
    // ──────────────────────────────────────────────────────────────

    public function test_readonly_fields_helper(): void
    {
        WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'customer_name',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);

        WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'total_amount',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'notes',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);

        $order = $this->createOrder();
        $readonly = $this->service->getReadonlyFields($order);

        $this->assertContains('customer_name', $readonly);
        $this->assertContains('notes', $readonly);
        $this->assertNotContains('total_amount', $readonly);
    }

    public function test_hidden_fields_helper(): void
    {
        WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'customer_name',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'secret_field',
            'visibility' => 'hidden',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'internal_code',
            'visibility' => 'visible',
            'mutability' => 'locked',
            'is_required' => false,
        ]);

        $order = $this->createOrder();
        $hidden = $this->service->getHiddenFields($order);

        // hidden visibility → included
        $this->assertContains('secret_field', $hidden);
        // locked mutability → included (even though visible)
        $this->assertContains('internal_code', $hidden);
        // visible + editable → NOT included
        $this->assertNotContains('customer_name', $hidden);
    }

    // ──────────────────────────────────────────────────────────────
    //  isFieldVisible / isFieldReadonly via HasStateAccess trait
    // ──────────────────────────────────────────────────────────────

    public function test_is_field_visible_returns_true_when_not_configured(): void
    {
        $order = $this->createOrder();

        // No field permissions configured → default visible
        $this->assertTrue($order->isFieldVisible('unconfigured_field', $this->admin));
    }

    public function test_is_field_visible_for_rm_field(): void
    {
        $field = WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'attachments',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => '@assigned',
            'visibility' => 'hidden',
            'mutability' => null,
            'is_required' => null,
        ]);

        $order = $this->createOrder();
        $order->assignTo($this->editor, 'primary', $this->admin);

        // Admin (not assigned) sees the field via base config
        $this->assertTrue($order->isFieldVisible('attachments', $this->admin));

        // Assigned user (editor) has it hidden via @assigned override
        $this->assertFalse($order->isFieldVisible('attachments', $this->editor));
    }

    public function test_is_field_visible_sub_field_action(): void
    {
        // claimAttachments.create visible by default, hidden for @assigned
        $field = WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'attachments.create',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => '@assigned',
            'visibility' => 'hidden',
            'mutability' => null,
            'is_required' => null,
        ]);

        $order = $this->createOrder();
        $order->assignTo($this->editor, 'primary', $this->admin);

        $this->assertTrue($order->isFieldVisible('attachments.create', $this->admin));
        $this->assertFalse($order->isFieldVisible('attachments.create', $this->editor));
    }

    public function test_is_field_readonly(): void
    {
        WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'notes',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);

        $order = $this->createOrder();

        $this->assertTrue($order->isFieldReadonly('notes', $this->admin));
        $this->assertFalse($order->isFieldReadonly('unconfigured', $this->admin));
    }

    // ──────────────────────────────────────────────────────────────
    //  Custom role resolver
    // ──────────────────────────────────────────────────────────────

    public function test_resolves_user_roles_via_configured_resolver(): void
    {
        // Set up a custom resolver that always adds 'custom_role'
        $resolverClass = new class extends DefaultRoleResolver
        {
            public function getRoles(Model $user): array
            {
                $roles = parent::getRoles($user);
                $roles[] = 'custom_role';

                return $roles;
            }
        };

        config()->set('filament-flow.state_access.role_resolver', get_class($resolverClass));
        app()->instance(get_class($resolverClass), $resolverClass);

        $field = WorkflowStateField::create([
            'state_id' => $this->draftState->id,
            'field_name' => 'special_field',
            'visibility' => 'hidden',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => 'custom_role',
            'visibility' => 'visible',
            'mutability' => null,
            'is_required' => null,
        ]);

        $order = $this->createOrder();
        $service = new WorkflowFieldPermissionsService;
        $perms = $service->getFieldPermissions($order, $this->editor);

        // custom_role override should make field visible
        $this->assertTrue($perms['special_field']['visible']);
    }
}
