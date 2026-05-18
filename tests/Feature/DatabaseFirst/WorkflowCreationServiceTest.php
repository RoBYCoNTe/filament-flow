<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Services\WorkflowCreationService;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class WorkflowCreationServiceTest extends TestCase
{
    private WorkflowCreationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WorkflowCreationService::class);
    }

    public function test_can_create_with_access_rule(): void
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

    public function test_can_create_with_role_rule(): void
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

        $admin = $this->createTestUser(['role' => 'admin']);
        $editor = $this->createTestUser(['email' => 'editor@test.com', 'role' => 'editor']);

        $this->assertTrue($this->service->canCreate(Order::class, $admin));
        $this->assertFalse($this->service->canCreate(Order::class, $editor));
    }

    public function test_can_create_falls_back_to_defaults_without_rules(): void
    {
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, [
            'name' => 'draft',
            'is_initial' => true,
        ]);

        $user = $this->createTestUser();

        // No explicit create access rules → falls back to config defaults (@authenticated)
        $this->assertTrue($this->service->canCreate(Order::class, $user));
    }

    public function test_cannot_create_with_wrong_role(): void
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

    public function test_create_record_sets_initial_state(): void
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

        $record = $this->service->createRecord(Order::class, [
            'order_number' => 'ORD-CREATE-001',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ], $user);

        $this->assertInstanceOf(Order::class, $record);
        $this->assertTrue($record->exists);
        $this->assertEquals('draft', $record->state);
    }

    public function test_create_record_auto_assigns_creator(): void
    {
        $workflow = $this->createTestWorkflow([
            'creation_policy' => [
                'auto_assign_creator' => true,
                'assignment_type' => 'primary',
            ],
        ]);
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

        $record = $this->service->createRecord(Order::class, [
            'order_number' => 'ORD-CREATE-002',
            'customer_name' => 'Test Customer',
            'total_amount' => 200.00,
        ], $user);

        $this->assertDatabaseHas('workflow_assignments', [
            'assignable_type' => Order::class,
            'assignable_id' => $record->id,
            'user_id' => $user->id,
            'assignment_type' => 'primary',
        ]);
    }

    public function test_create_record_auto_sets_user_id(): void
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

        $record = $this->service->createRecord(Order::class, [
            'order_number' => 'ORD-USERID-001',
            'customer_name' => 'Test',
            'total_amount' => 100,
        ], $user);

        $this->assertEquals($user->id, $record->user_id);
    }

    public function test_create_record_does_not_overwrite_provided_user_id(): void
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
        $otherUser = $this->createTestUser([
            'email' => 'other@example.com',
            'name' => 'Other User',
        ]);

        $record = $this->service->createRecord(Order::class, [
            'order_number' => 'ORD-USERID-002',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'user_id' => $otherUser->id,
        ], $user);

        // Should keep the explicitly provided user_id
        $this->assertEquals($otherUser->id, $record->user_id);
    }

    public function test_create_record_respects_configurable_owner_field(): void
    {
        config()->set('filament-flow.state_access.owner_field', 'user_id');

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

        $record = $this->service->createRecord(Order::class, [
            'order_number' => 'ORD-USERID-003',
            'customer_name' => 'Test',
            'total_amount' => 100,
        ], $user);

        $this->assertEquals($user->id, $record->user_id);
    }

    public function test_create_record_throws_without_workflow(): void
    {
        $user = $this->createTestUser();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No active workflow found');

        // No workflow registered for a fictitious model
        $this->service->createRecord('App\\Models\\FakeModel', [], $user);
    }

    public function test_create_record_throws_without_permission(): void
    {
        $workflow = $this->createTestWorkflow();
        $initialState = $this->createWorkflowState($workflow, [
            'name' => 'draft',
            'is_initial' => true,
        ]);

        // Restrict creation to admin role only
        WorkflowStateAccessRule::create([
            'state_id' => $initialState->id,
            'access_type' => 'create',
            'rule' => 'role:admin',
            'priority' => 0,
            'is_active' => true,
        ]);

        $editor = $this->createTestUser(['role' => 'editor']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User not authorized');

        $this->service->createRecord(Order::class, [
            'order_number' => 'ORD-DENIED',
            'customer_name' => 'Test',
            'total_amount' => 50,
        ], $editor);
    }

    public function test_create_record_throws_without_initial_state(): void
    {
        $workflow = $this->createTestWorkflow();
        // No states at all
        // canCreate will fail first because no initial state → no rules
        // We need to create a state with create rule but NOT initial

        $state = $this->createWorkflowState($workflow, [
            'name' => 'active',
            'is_initial' => false,
        ]);

        // Even with an access rule, there's no initial state
        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'create',
            'rule' => '@authenticated',
            'priority' => 0,
            'is_active' => true,
        ]);

        $user = $this->createTestUser();

        $this->expectException(\Exception::class);

        $this->service->createRecord(Order::class, [
            'order_number' => 'ORD-NOINIT',
            'customer_name' => 'Test',
            'total_amount' => 50,
        ], $user);
    }
}
