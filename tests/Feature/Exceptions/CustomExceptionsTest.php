<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Exceptions;

use Exception;
use RoBYCoNTe\FilamentFlow\Exceptions\ActionNotFoundException;
use RoBYCoNTe\FilamentFlow\Exceptions\AuthenticationRequiredException;
use RoBYCoNTe\FilamentFlow\Exceptions\ConditionNotMetException;
use RoBYCoNTe\FilamentFlow\Exceptions\InitialStateNotFoundException;
use RoBYCoNTe\FilamentFlow\Exceptions\InvalidComponentException;
use RoBYCoNTe\FilamentFlow\Exceptions\InvalidStateException;
use RoBYCoNTe\FilamentFlow\Exceptions\StateDeletionException;
use RoBYCoNTe\FilamentFlow\Exceptions\WorkflowNotFoundException;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateAccessRule;
use RoBYCoNTe\FilamentFlow\Services\WorkflowCreationService;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class CustomExceptionsTest extends TestCase
{
    // --- Unit tests: message + properties ---

    public function test_authentication_required_exception(): void
    {
        $e = new AuthenticationRequiredException;
        $this->assertInstanceOf(Exception::class, $e);
        $this->assertEquals('User not authenticated', $e->getMessage());
    }

    public function test_authentication_required_exception_custom_message(): void
    {
        $e = new AuthenticationRequiredException('Custom auth message');
        $this->assertEquals('Custom auth message', $e->getMessage());
    }

    public function test_workflow_not_found_exception(): void
    {
        $e = new WorkflowNotFoundException(Order::class);
        $this->assertInstanceOf(Exception::class, $e);
        $this->assertStringContainsString(Order::class, $e->getMessage());
        $this->assertEquals(Order::class, $e->modelClass);
    }

    public function test_invalid_state_exception(): void
    {
        $e = new InvalidStateException;
        $this->assertInstanceOf(Exception::class, $e);
        $this->assertStringContainsString('not a valid State instance', $e->getMessage());
    }

    public function test_initial_state_not_found_exception(): void
    {
        $e = new InitialStateNotFoundException;
        $this->assertInstanceOf(Exception::class, $e);
        $this->assertStringContainsString('No initial state', $e->getMessage());
    }

    public function test_action_not_found_exception(): void
    {
        $e = new ActionNotFoundException('approve');
        $this->assertInstanceOf(Exception::class, $e);
        $this->assertStringContainsString('approve', $e->getMessage());
        $this->assertEquals('approve', $e->actionName);
    }

    public function test_condition_not_met_exception(): void
    {
        $e = new ConditionNotMetException('submit');
        $this->assertInstanceOf(Exception::class, $e);
        $this->assertStringContainsString('submit', $e->getMessage());
        $this->assertEquals('submit', $e->actionName);
    }

    public function test_invalid_component_exception(): void
    {
        $e = new InvalidComponentException('notes');
        $this->assertInstanceOf(Exception::class, $e);
        $this->assertStringContainsString('notes', $e->getMessage());
        $this->assertEquals('notes', $e->fieldName);
    }

    public function test_state_deletion_exception(): void
    {
        $e = new StateDeletionException;
        $this->assertInstanceOf(Exception::class, $e);
        $this->assertStringContainsString('Cannot delete state', $e->getMessage());
    }

    // --- Integration: thrown in the right place ---

    public function test_workflow_not_found_thrown_on_create(): void
    {
        $user = $this->createTestUser();

        $this->expectException(WorkflowNotFoundException::class);

        app(WorkflowCreationService::class)
            ->createRecord('App\\Models\\NonExistent', [], $user);
    }

    public function test_initial_state_not_found_thrown_on_create(): void
    {
        $workflow = $this->createTestWorkflow();
        // State without is_initial
        $state = $this->createWorkflowState($workflow, [
            'name' => 'active',
            'is_initial' => false,
        ]);

        WorkflowStateAccessRule::create([
            'state_id' => $state->id,
            'access_type' => 'create',
            'rule' => '@authenticated',
            'priority' => 0,
            'is_active' => true,
        ]);

        $user = $this->createTestUser();

        $this->expectException(InitialStateNotFoundException::class);

        app(WorkflowCreationService::class)
            ->createRecord(Order::class, [
                'order_number' => 'ORD-NO-INIT',
                'customer_name' => 'Test',
                'total_amount' => 10,
            ], $user);
    }

    public function test_action_not_found_thrown_on_execute_action(): void
    {
        $workflow = $this->createTestWorkflow();
        $state = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'is_initial' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ACTION',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'pending',
        ]);

        $this->expectException(ActionNotFoundException::class);

        $order->executeAction('nonexistent_action');
    }

    public function test_condition_not_met_thrown_on_execute_action(): void
    {
        $workflow = $this->createTestWorkflow();
        $state = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'is_initial' => true,
        ]);

        // Create a self-transition (action) with a failing condition
        $transition = $this->createWorkflowTransition($workflow, $state, $state, [
            'name' => 'approve',
            'to_state_id' => null,
            'conditions' => [
                ['field' => 'total_amount', 'operator' => '>', 'value' => 99999],
            ],
        ]);

        $order = Order::create([
            'order_number' => 'ORD-COND',
            'customer_name' => 'Test',
            'total_amount' => 100,
            'state' => 'pending',
        ]);

        $this->expectException(ConditionNotMetException::class);

        $order->executeAction('approve');
    }
}
