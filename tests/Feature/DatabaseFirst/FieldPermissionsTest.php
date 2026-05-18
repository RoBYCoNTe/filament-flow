<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Models\WorkflowStateField;
use RoBYCoNTe\FilamentFlow\Services\WorkflowFieldPermissionsService;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Test WorkflowFieldPermissionsService for field visibility, mutability, and validation per state
 */
class FieldPermissionsTest extends TestCase
{
    protected WorkflowFieldPermissionsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WorkflowFieldPermissionsService;
    }

    /**
     * Test getFieldPermissions returns empty array when no workflow exists
     */
    public function test_returns_empty_when_no_workflow(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-PERM-001',
            'customer_name' => 'John Doe',
            'total_amount' => 100.00,
            'state' => PendingState::class,
        ]);

        // No workflow created
        $permissions = $this->service->getFieldPermissions($order);

        $this->assertIsArray($permissions);
        $this->assertEmpty($permissions);
    }

    /**
     * Test getFieldPermissions returns empty array when state not found
     */
    public function test_returns_empty_when_state_not_found(): void
    {
        $workflow = $this->createTestWorkflow();

        // Create a state but not matching the order's state
        $this->createWorkflowState($workflow, [
            'name' => 'different_state',
            'class_name' => 'DifferentState',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-PERM-002',
            'customer_name' => 'Jane Doe',
            'total_amount' => 150.00,
            'state' => PendingState::class,
        ]);

        $permissions = $this->service->getFieldPermissions($order);

        $this->assertIsArray($permissions);
        $this->assertEmpty($permissions);
    }

    /**
     * Test getFieldPermissions returns correct field configuration
     */
    public function test_returns_field_permissions_for_state(): void
    {
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        // Create field permissions
        WorkflowStateField::create([
            'state_id' => $state->id,
            'field_name' => 'customer_name',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => true,
            'validation_rules' => ['required', 'string', 'max:255'],
        ]);

        WorkflowStateField::create([
            'state_id' => $state->id,
            'field_name' => 'total_amount',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
            'validation_rules' => null,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-PERM-003',
            'customer_name' => 'Bob Smith',
            'total_amount' => 200.00,
            'state' => PendingState::class,
        ]);

        $permissions = $this->service->getFieldPermissions($order);

        $this->assertArrayHasKey('customer_name', $permissions);
        $this->assertArrayHasKey('total_amount', $permissions);

        // Check customer_name permissions
        $this->assertTrue($permissions['customer_name']['visible']);
        $this->assertFalse($permissions['customer_name']['readonly']);
        $this->assertTrue($permissions['customer_name']['required']);
        $this->assertEquals(['required', 'string', 'max:255'], $permissions['customer_name']['validation']);

        // Check total_amount permissions
        $this->assertTrue($permissions['total_amount']['visible']);
        $this->assertTrue($permissions['total_amount']['readonly']);
        $this->assertFalse($permissions['total_amount']['required']);
    }

    /**
     * Test getFieldPermissions handles hidden fields
     */
    public function test_handles_hidden_fields(): void
    {
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        WorkflowStateField::create([
            'state_id' => $state->id,
            'field_name' => 'notes',
            'visibility' => 'hidden',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-PERM-004',
            'customer_name' => 'Alice Johnson',
            'total_amount' => 250.00,
            'state' => PendingState::class,
        ]);

        $permissions = $this->service->getFieldPermissions($order);

        $this->assertArrayHasKey('notes', $permissions);
        $this->assertFalse($permissions['notes']['visible']);
    }

    /**
     * Test getReadonlyFields returns correct list
     */
    public function test_get_readonly_fields(): void
    {
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'class_name' => ProcessingState::class,
        ]);

        WorkflowStateField::create([
            'state_id' => $state->id,
            'field_name' => 'order_number',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);

        WorkflowStateField::create([
            'state_id' => $state->id,
            'field_name' => 'customer_name',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);

        WorkflowStateField::create([
            'state_id' => $state->id,
            'field_name' => 'notes',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-PERM-005',
            'customer_name' => 'Charlie Brown',
            'total_amount' => 300.00,
            'state' => ProcessingState::class,
        ]);

        $readonlyFields = $this->service->getReadonlyFields($order);

        $this->assertContains('order_number', $readonlyFields);
        $this->assertContains('customer_name', $readonlyFields);
        $this->assertNotContains('notes', $readonlyFields);
    }

    /**
     * Test getHiddenFields returns correct list
     */
    public function test_get_hidden_fields(): void
    {
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        WorkflowStateField::create([
            'state_id' => $state->id,
            'field_name' => 'internal_notes',
            'visibility' => 'hidden',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        WorkflowStateField::create([
            'state_id' => $state->id,
            'field_name' => 'admin_comments',
            'visibility' => 'hidden',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        WorkflowStateField::create([
            'state_id' => $state->id,
            'field_name' => 'customer_name',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-PERM-006',
            'customer_name' => 'David Lee',
            'total_amount' => 350.00,
            'state' => PendingState::class,
        ]);

        $hiddenFields = $this->service->getHiddenFields($order);

        $this->assertContains('internal_notes', $hiddenFields);
        $this->assertContains('admin_comments', $hiddenFields);
        $this->assertNotContains('customer_name', $hiddenFields);
    }

    /**
     * Test permissions change with state
     */
    public function test_permissions_change_with_state(): void
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

        // In pending state, customer_name is editable
        WorkflowStateField::create([
            'state_id' => $pendingState->id,
            'field_name' => 'customer_name',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => true,
        ]);

        // In processing state, customer_name is readonly
        WorkflowStateField::create([
            'state_id' => $processingState->id,
            'field_name' => 'customer_name',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => false,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-PERM-007',
            'customer_name' => 'Eve Martin',
            'total_amount' => 400.00,
            'state' => PendingState::class,
        ]);

        // Check permissions in pending state
        $pendingPermissions = $this->service->getFieldPermissions($order);
        $this->assertFalse($pendingPermissions['customer_name']['readonly']);
        $this->assertTrue($pendingPermissions['customer_name']['required']);

        // Change state to processing
        $order->state = ProcessingState::class;
        $order->save();
        $order->refresh();

        // Check permissions in processing state
        $processingPermissions = $this->service->getFieldPermissions($order);
        $this->assertTrue($processingPermissions['customer_name']['readonly']);
        $this->assertFalse($processingPermissions['customer_name']['required']);
    }

    /**
     * Test state lookup by name (for database-only states)
     */
    public function test_state_lookup_by_name(): void
    {
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, [
            'name' => 'custom_db_state',
            'class_name' => 'custom_db_state', // Same as name for DB-only
        ]);

        WorkflowStateField::create([
            'state_id' => $state->id,
            'field_name' => 'notes',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-PERM-008',
            'customer_name' => 'Frank Wilson',
            'total_amount' => 450.00,
        ]);

        $order->state = 'custom_db_state';
        $order->save();
        $order->refresh();

        $permissions = $this->service->getFieldPermissions($order);

        $this->assertArrayHasKey('notes', $permissions);
        $this->assertTrue($permissions['notes']['required']);
    }

    /**
     * Test empty readonly fields returns empty array
     */
    public function test_empty_readonly_fields(): void
    {
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        // All fields are editable
        WorkflowStateField::create([
            'state_id' => $state->id,
            'field_name' => 'customer_name',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-PERM-009',
            'customer_name' => 'Grace Taylor',
            'total_amount' => 500.00,
            'state' => PendingState::class,
        ]);

        $readonlyFields = $this->service->getReadonlyFields($order);

        $this->assertIsArray($readonlyFields);
        $this->assertEmpty($readonlyFields);
    }

    /**
     * Test empty hidden fields returns empty array
     */
    public function test_empty_hidden_fields(): void
    {
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        // All fields are visible
        WorkflowStateField::create([
            'state_id' => $state->id,
            'field_name' => 'customer_name',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-PERM-010',
            'customer_name' => 'Henry Adams',
            'total_amount' => 550.00,
            'state' => PendingState::class,
        ]);

        $hiddenFields = $this->service->getHiddenFields($order);

        $this->assertIsArray($hiddenFields);
        $this->assertEmpty($hiddenFields);
    }

    /**
     * Test validation rules are correctly returned
     */
    public function test_validation_rules(): void
    {
        $workflow = $this->createTestWorkflow();

        $state = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        WorkflowStateField::create([
            'state_id' => $state->id,
            'field_name' => 'customer_email',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => true,
            'validation_rules' => ['required', 'email', 'max:255'],
        ]);

        $order = Order::create([
            'order_number' => 'ORD-PERM-011',
            'customer_name' => 'Ivy Chen',
            'total_amount' => 600.00,
            'state' => PendingState::class,
        ]);

        $permissions = $this->service->getFieldPermissions($order);

        $this->assertArrayHasKey('customer_email', $permissions);
        $this->assertEquals(['required', 'email', 'max:255'], $permissions['customer_email']['validation']);
    }

    /**
     * Test inactive workflow is ignored
     */
    public function test_inactive_workflow_ignored(): void
    {
        $workflow = $this->createTestWorkflow(['is_active' => false]);

        $state = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        WorkflowStateField::create([
            'state_id' => $state->id,
            'field_name' => 'customer_name',
            'visibility' => 'visible',
            'mutability' => 'readonly',
            'is_required' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-PERM-012',
            'customer_name' => 'Jack Miller',
            'total_amount' => 650.00,
            'state' => PendingState::class,
        ]);

        $permissions = $this->service->getFieldPermissions($order);

        // Should return empty because workflow is inactive
        $this->assertEmpty($permissions);
    }
}
