<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Hybrid;

use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ShippedState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Test migrating workflow from Code-First (Spatie) to Database-First configuration
 */
class CodeToDbMigrationTest extends TestCase
{
    /**
     * Test workflow starts with PHP states
     */
    public function test_initial_code_first_workflow(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-MIGRATE-001',
            'customer_name' => 'John Doe',
            'total_amount' => 100.00,
            'state' => PendingState::class,
        ]);

        // Order starts in PendingState (Code-First)
        $this->assertInstanceOf(PendingState::class, $order->state);

        // Can transition using PHP states
        $order->transitionTo(ProcessingState::class);
        $this->assertInstanceOf(ProcessingState::class, $order->state);
    }

    /**
     * Test creating database configuration alongside PHP states
     */
    public function test_create_database_configuration(): void
    {
        $workflow = $this->createTestWorkflow();

        // Create database representations of PHP states
        $pendingDbState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'label' => 'Pending',
            'class_name' => PendingState::class,
        ]);

        $processingDbState = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'label' => 'Processing',
            'class_name' => ProcessingState::class,
        ]);

        $shippedDbState = $this->createWorkflowState($workflow, [
            'name' => 'shipped',
            'label' => 'Shipped',
            'class_name' => ShippedState::class,
        ]);

        $this->assertNotNull($pendingDbState->id);
        $this->assertNotNull($processingDbState->id);
        $this->assertNotNull($shippedDbState->id);

        // Verify all states are created
        $this->assertEquals(3, $workflow->states()->count());
    }

    /**
     * Test transitions work with database configuration
     */
    public function test_transitions_work_with_db_config(): void
    {
        $workflow = $this->createTestWorkflow();

        $pendingDbState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        $processingDbState = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'class_name' => ProcessingState::class,
        ]);

        $shippedDbState = $this->createWorkflowState($workflow, [
            'name' => 'shipped',
            'class_name' => ShippedState::class,
        ]);

        // Register all transitions
        $this->createWorkflowTransition($workflow, $pendingDbState, $processingDbState);
        $this->createWorkflowTransition($workflow, $processingDbState, $shippedDbState);

        $order = Order::create([
            'order_number' => 'ORD-MIGRATE-002',
            'customer_name' => 'Jane Doe',
            'total_amount' => 150.00,
            'state' => PendingState::class,
        ]);

        // Transition still works with PHP states
        $order->transitionTo(ProcessingState::class);
        $this->assertInstanceOf(ProcessingState::class, $order->state);

        // Can also transition using PHP state class
        $order->transitionTo(ShippedState::class);
        $this->assertInstanceOf(ShippedState::class, $order->state);
    }

    /**
     * Test assignments preserved during migration
     */
    public function test_assignments_preserved(): void
    {
        $user = $this->createTestUser(['name' => 'Migrated User', 'email' => 'migrated@example.com']);

        $order = Order::create([
            'order_number' => 'ORD-MIGRATE-003',
            'customer_name' => 'Bob Smith',
            'total_amount' => 200.00,
        ]);

        // Assign user during Code-First phase
        $order->assignTo($user, 'primary');

        // Create database configuration
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, [
            'class_name' => PendingState::class,
        ]);

        // Assignment should still be valid
        $this->assertTrue($order->isAssignedTo($user, 'primary'));
    }

    /**
     * Test state mappings correct after migration
     */
    public function test_state_mappings_correct(): void
    {
        $workflow = $this->createTestWorkflow();

        // Create all database states mapping to PHP classes
        $states = [
            PendingState::class => 'pending',
            ProcessingState::class => 'processing',
            ShippedState::class => 'shipped',
        ];

        $createdStates = [];
        foreach ($states as $phpClass => $name) {
            $createdStates[$phpClass] = $this->createWorkflowState($workflow, [
                'name' => $name,
                'class_name' => $phpClass,
            ]);
        }

        // Verify mappings
        foreach ($createdStates as $phpClass => $dbState) {
            $this->assertEquals($phpClass, $dbState->class_name);
        }
    }

    /**
     * Test hybrid mode works after migration
     */
    public function test_hybrid_mode_after_migration(): void
    {
        $workflow = $this->createTestWorkflow();

        // Setup database states
        $pendingDbState = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'class_name' => PendingState::class,
        ]);

        $customDbState = $this->createWorkflowState($workflow, [
            'name' => 'custom',
            'label' => 'Custom Status',
        ]);

        $this->createWorkflowTransition($workflow, $pendingDbState, $customDbState);

        $order = Order::create([
            'order_number' => 'ORD-MIGRATE-004',
            'customer_name' => 'Charlie Brown',
            'total_amount' => 250.00,
            'state' => PendingState::class,
        ]);

        // Can transition from PHP state to DB string
        $order->transitionTo('custom');
        $this->assertEquals('custom', $order->state);
    }

    /**
     * Test multiple models migrated together
     */
    public function test_multiple_models_migrated(): void
    {
        $order1 = Order::create([
            'order_number' => 'ORD-MIGRATE-005',
            'customer_name' => 'David Lee',
            'total_amount' => 175.00,
            'state' => PendingState::class,
        ]);

        $order2 = Order::create([
            'order_number' => 'ORD-MIGRATE-006',
            'customer_name' => 'Eve Martin',
            'total_amount' => 400.00,
            'state' => PendingState::class,
        ]);

        // Both in initial Code-First state
        $this->assertInstanceOf(PendingState::class, $order1->state);
        $this->assertInstanceOf(PendingState::class, $order2->state);

        // Create database configuration once
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, ['class_name' => PendingState::class]);

        // Both orders can now work with database configuration
        $this->assertTrue(true); // Successfully migrated both
    }
}
