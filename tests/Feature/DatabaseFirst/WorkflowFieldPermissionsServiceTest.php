<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Models\WorkflowStateField;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateFieldRole;
use RoBYCoNTe\FilamentFlow\Services\WorkflowFieldPermissionsService;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class WorkflowFieldPermissionsServiceTest extends TestCase
{
    private WorkflowFieldPermissionsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WorkflowFieldPermissionsService::class);
    }

    public function test_get_field_permissions_returns_empty_without_workflow(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FP-001',
            'customer_name' => 'Test',
            'total_amount' => 100,
        ]);

        $result = $this->service->getFieldPermissions($order);
        $this->assertEmpty($result);
    }

    public function test_get_field_permissions_for_state(): void
    {
        $workflow = $this->createTestWorkflow();
        $state = $this->createWorkflowState($workflow, ['name' => 'pending']);

        WorkflowStateField::create([
            'state_id' => $state->id,
            'field_name' => 'notes',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => false,
            'sort_order' => 0,
        ]);

        WorkflowStateField::create([
            'state_id' => $state->id,
            'field_name' => 'internal_code',
            'visibility' => 'hidden',
            'mutability' => 'readonly',
            'is_required' => false,
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-FP-002',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'pending',
        ]);

        $permissions = $this->service->getFieldPermissions($order);

        $this->assertArrayHasKey('notes', $permissions);
        $this->assertTrue($permissions['notes']['visible']);
        $this->assertFalse($permissions['notes']['readonly']);

        $this->assertArrayHasKey('internal_code', $permissions);
        $this->assertFalse($permissions['internal_code']['visible']);
        $this->assertTrue($permissions['internal_code']['readonly']);
    }

    public function test_locked_mutability(): void
    {
        $workflow = $this->createTestWorkflow();
        $state = $this->createWorkflowState($workflow, ['name' => 'completed']);

        WorkflowStateField::create([
            'state_id' => $state->id,
            'field_name' => 'notes',
            'visibility' => 'visible',
            'mutability' => 'locked',
            'is_required' => false,
            'sort_order' => 0,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-FP-003',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'completed',
        ]);

        $permissions = $this->service->getFieldPermissions($order);

        $this->assertTrue($permissions['notes']['locked']);
    }

    public function test_role_override_changes_visibility(): void
    {
        $workflow = $this->createTestWorkflow();
        $state = $this->createWorkflowState($workflow, ['name' => 'pending']);

        $field = WorkflowStateField::create([
            'state_id' => $state->id,
            'field_name' => 'secret_field',
            'visibility' => 'hidden',
            'mutability' => 'readonly',
            'is_required' => false,
            'sort_order' => 0,
        ]);

        // Admin override: make it visible
        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => 'admin',
            'visibility' => 'visible',
            'mutability' => 'editable',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-FP-004',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'pending',
        ]);

        $admin = $this->createTestUser(['role' => 'admin']);
        $editor = $this->createTestUser(['email' => 'editor@test.com', 'role' => 'editor']);

        $adminPermissions = $this->service->getFieldPermissions($order, $admin);
        $editorPermissions = $this->service->getFieldPermissions($order, $editor);

        $this->assertTrue($adminPermissions['secret_field']['visible']);
        $this->assertFalse($adminPermissions['secret_field']['readonly']);

        $this->assertFalse($editorPermissions['secret_field']['visible']);
        $this->assertTrue($editorPermissions['secret_field']['readonly']);
    }

    public function test_get_readonly_fields(): void
    {
        $workflow = $this->createTestWorkflow();
        $state = $this->createWorkflowState($workflow, ['name' => 'locked']);

        WorkflowStateField::create([
            'state_id' => $state->id,
            'field_name' => 'notes',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'sort_order' => 0,
        ]);

        WorkflowStateField::create([
            'state_id' => $state->id,
            'field_name' => 'name',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'sort_order' => 1,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-FP-005',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'locked',
        ]);

        $readonly = $this->service->getReadonlyFields($order);
        $this->assertEquals(['notes'], $readonly);
    }

    public function test_get_hidden_fields(): void
    {
        $workflow = $this->createTestWorkflow();
        $state = $this->createWorkflowState($workflow, ['name' => 'review']);

        WorkflowStateField::create([
            'state_id' => $state->id,
            'field_name' => 'hidden_field',
            'visibility' => 'hidden',
            'mutability' => 'readonly',
            'sort_order' => 0,
        ]);

        WorkflowStateField::create([
            'state_id' => $state->id,
            'field_name' => 'locked_field',
            'visibility' => 'visible',
            'mutability' => 'locked',
            'sort_order' => 1,
        ]);

        WorkflowStateField::create([
            'state_id' => $state->id,
            'field_name' => 'visible_field',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'sort_order' => 2,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-FP-006',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'review',
        ]);

        $hidden = $this->service->getHiddenFields($order);
        $this->assertContains('hidden_field', $hidden);
        $this->assertContains('locked_field', $hidden);
        $this->assertNotContains('visible_field', $hidden);
    }

    public function test_get_creation_field_permissions(): void
    {
        $workflow = $this->createTestWorkflow();
        $initialState = $this->createWorkflowState($workflow, ['name' => 'draft', 'is_initial' => true]);

        WorkflowStateField::create([
            'state_id' => $initialState->id,
            'field_name' => 'customer_name',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => true,
            'sort_order' => 0,
        ]);

        $user = $this->createTestUser();
        $permissions = $this->service->getCreationFieldPermissions(Order::class, $user);

        $this->assertArrayHasKey('customer_name', $permissions);
        $this->assertTrue($permissions['customer_name']['visible']);
        $this->assertTrue($permissions['customer_name']['required']);
    }

    public function test_get_table_column_permissions(): void
    {
        $workflow = $this->createTestWorkflow();
        $s1 = $this->createWorkflowState($workflow, ['name' => 'draft']);
        $s2 = $this->createWorkflowState($workflow, ['name' => 'active']);

        // Hidden in draft, visible in active
        WorkflowStateField::create([
            'state_id' => $s1->id,
            'field_name' => 'notes',
            'visibility' => 'hidden',
            'mutability' => 'readonly',
            'sort_order' => 0,
        ]);

        WorkflowStateField::create([
            'state_id' => $s2->id,
            'field_name' => 'notes',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'sort_order' => 0,
        ]);

        $permissions = $this->service->getTableColumnPermissions(Order::class);

        // Visible in at least one state → visible
        $this->assertTrue($permissions['notes']['visible']);
    }
}
