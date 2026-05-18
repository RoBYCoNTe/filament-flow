<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\CodeFirst;

use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\DeliveredState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ShippedState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Transitions\ToShippedTransition;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;
use RuntimeException;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

/**
 * Integration Tests: Transitions with User Assignment
 *
 * These tests verify that:
 * - Users can be assigned during state transitions
 * - Assignment validation works (e.g., required assignments)
 * - Multiple users can be assigned with different types
 * - Assignments persist correctly in the database
 * - Assignments work with the workflow assignment trait
 */
class TransitionWithUserAssignmentTest extends TestCase
{
    // ==========================================
    // INTEGRATION TESTS - User Assignment Flow
    // ==========================================

    /**
     * Test that users can be assigned during transition
     *
     * @throws CouldNotPerformTransition
     */
    public function test_users_assigned_during_transition(): void
    {
        $user1 = $this->createTestUser(['name' => 'Shipper 1', 'email' => 'shipper1@test.com']);
        $user2 = $this->createTestUser(['name' => 'Shipper 2', 'email' => 'shipper2@test.com']);

        $order = Order::create([
            'order_number' => 'ORD-ASSIGN-001',
            'customer_name' => 'John Doe',
            'total_amount' => 100.00,
            'processed_at' => now(),
        ]);

        // Start in processing state
        $order->state = new ProcessingState($order);
        $order->save();

        // Transition to shipped with user assignments
        $order->state->transitionTo(ShippedState::class, [
            'assigned_users' => [$user1->id, $user2->id],
            'tracking_number' => 'TRK001',
            'carrier' => 'ups',
        ]);

        $order->refresh();

        // Verify users are assigned
        $this->assertTrue($order->isAssignedTo($user1, 'secondary'));
        $this->assertTrue($order->isAssignedTo($user2, 'secondary'));
    }

    /**
     * Test that single user can be assigned
     *
     * @throws CouldNotPerformTransition
     */
    public function test_single_user_assigned_during_transition(): void
    {
        $user = $this->createTestUser(['name' => 'Solo Shipper', 'email' => 'solo@test.com']);

        $order = Order::create([
            'order_number' => 'ORD-ASSIGN-002',
            'customer_name' => 'Jane Doe',
            'total_amount' => 150.00,
            'processed_at' => now(),
        ]);

        $order->state = new ProcessingState($order);
        $order->save();

        $order->state->transitionTo(ShippedState::class, [
            'assigned_users' => [$user->id],
            'tracking_number' => 'TRK002',
            'carrier' => 'fedex',
        ]);

        $order->refresh();

        $this->assertTrue($order->isAssignedTo($user, 'secondary'));
        $this->assertCount(1, $order->getAssignedUsers());
    }

    /**
     * Test transition fails without required user assignment
     *
     * @throws CouldNotPerformTransition
     */
    public function test_transition_fails_without_user_assignment(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-ASSIGN-003',
            'customer_name' => 'Bob Smith',
            'total_amount' => 200.00,
            'processed_at' => now(),
        ]);

        $order->state = new ProcessingState($order);
        $order->save();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('At least one user must be assigned to ship the order.');

        // Try to transition without assigning users
        $order->state->transitionTo(ShippedState::class, [
            'assigned_users' => [],
            'tracking_number' => 'TRK003',
            'carrier' => 'dhl',
        ]);
    }

    /**
     * Test that existing assignments satisfy requirement
     *
     * @throws CouldNotPerformTransition
     */
    public function test_existing_assignments_satisfy_requirement(): void
    {
        $user = $this->createTestUser(['name' => 'Pre-assigned', 'email' => 'pre@test.com']);

        $order = Order::create([
            'order_number' => 'ORD-ASSIGN-004',
            'customer_name' => 'Alice Johnson',
            'total_amount' => 300.00,
            'processed_at' => now(),
        ]);

        // Pre-assign user
        $order->assignTo($user, 'primary');

        $order->state = new ProcessingState($order);
        $order->save();

        // Transition without new assignments - should work because user is already assigned
        $order->state->transitionTo(ShippedState::class, [
            'assigned_users' => [],
            'tracking_number' => 'TRK004',
            'carrier' => 'usps',
        ]);

        $order->refresh();

        $this->assertInstanceOf(ShippedState::class, $order->state);
    }

    /**
     * Test assignments persist across multiple transitions
     *
     * @throws CouldNotPerformTransition
     */
    public function test_assignments_persist_across_transitions(): void
    {
        $processor = $this->createTestUser(['name' => 'Processor', 'email' => 'processor@test.com']);
        $shipper = $this->createTestUser(['name' => 'Shipper', 'email' => 'shipper@test.com']);

        $order = Order::create([
            'order_number' => 'ORD-ASSIGN-005',
            'customer_name' => 'Charlie Brown',
            'total_amount' => 250.00,
        ]);

        // Set initial state
        $order->state = new PendingState($order);
        $order->save();

        // Assign processor during first transition
        $order->assignTo($processor, 'primary');

        // Transition to processing
        $order->state->transitionTo(ProcessingState::class, [
            'processing_notes' => 'Assigned to processor',
        ]);
        $order->refresh();

        $this->assertTrue($order->isAssignedTo($processor, 'primary'));

        // Transition to shipped with shipper
        $order->state->transitionTo(ShippedState::class, [
            'assigned_users' => [$shipper->id],
            'tracking_number' => 'TRK005',
            'carrier' => 'ups',
        ]);
        $order->refresh();

        // Both assignments should exist
        $this->assertTrue($order->isAssignedTo($processor, 'primary'));
        $this->assertTrue($order->isAssignedTo($shipper, 'secondary'));
    }

    /**
     * Test different assignment types during different transitions
     *
     * @throws CouldNotPerformTransition
     */
    public function test_different_assignment_types(): void
    {
        $user = $this->createTestUser(['name' => 'Multi-role', 'email' => 'multi@test.com']);

        $order = Order::create([
            'order_number' => 'ORD-ASSIGN-006',
            'customer_name' => 'David Lee',
            'total_amount' => 175.00,
            'processed_at' => now(),
        ]);

        // Assign as primary
        $order->assignTo($user, 'primary');

        $order->state = new ProcessingState($order);
        $order->save();

        // Also assign as shipper during transition
        $order->state->transitionTo(ShippedState::class, [
            'assigned_users' => [$user->id],
            'tracking_number' => 'TRK006',
            'carrier' => 'fedex',
        ]);

        $order->refresh();

        // User should have both assignment types
        $types = $order->getAssignmentTypesForUser($user);
        $this->assertContains('primary', $types);
        $this->assertContains('secondary', $types);
    }

    /**
     * Test retrieving assigned users after transition
     *
     * @throws CouldNotPerformTransition
     */
    public function test_get_assigned_users_after_transition(): void
    {
        $user1 = $this->createTestUser(['name' => 'User 1', 'email' => 'user1@test.com']);
        $user2 = $this->createTestUser(['name' => 'User 2', 'email' => 'user2@test.com']);
        $user3 = $this->createTestUser(['name' => 'User 3', 'email' => 'user3@test.com']);

        $order = Order::create([
            'order_number' => 'ORD-ASSIGN-007',
            'customer_name' => 'Eve Martin',
            'total_amount' => 400.00,
            'processed_at' => now(),
        ]);

        $order->state = new ProcessingState($order);
        $order->save();

        $order->state->transitionTo(ShippedState::class, [
            'assigned_users' => [$user1->id, $user2->id, $user3->id],
            'tracking_number' => 'TRK007',
            'carrier' => 'dhl',
        ]);

        $order->refresh();

        $assignedUsers = $order->getAssignedUsers();
        $this->assertCount(3, $assignedUsers);

        $assignedUserIds = $order->getAssignedUserIds();
        $this->assertContains($user1->id, $assignedUserIds);
        $this->assertContains($user2->id, $assignedUserIds);
        $this->assertContains($user3->id, $assignedUserIds);
    }

    /**
     * Test assignment with transition data persistence
     *
     * @throws CouldNotPerformTransition
     */
    public function test_assignment_with_transition_data_persistence(): void
    {
        $user = $this->createTestUser(['name' => 'Full Test', 'email' => 'full@test.com']);

        $order = Order::create([
            'order_number' => 'ORD-ASSIGN-008',
            'customer_name' => 'Frank Miller',
            'total_amount' => 550.00,
            'processed_at' => now(),
        ]);

        $orderId = $order->id;

        $order->state = new ProcessingState($order);
        $order->save();

        $order->state->transitionTo(ShippedState::class, [
            'assigned_users' => [$user->id],
            'tracking_number' => 'TRK-PERSIST-001',
            'carrier' => 'ups',
            'shipping_notes' => 'Persistence test',
        ]);

        // Completely fresh query
        $freshOrder = Order::find($orderId);

        $this->assertInstanceOf(ShippedState::class, $freshOrder->state);
        $this->assertEquals('TRK-PERSIST-001', $freshOrder->tracking_number);
        $this->assertEquals('ups', $freshOrder->carrier);
        $this->assertEquals('Persistence test', $freshOrder->shipping_notes);
        $this->assertTrue($freshOrder->isAssignedTo($user, 'secondary'));
    }

    // ==========================================
    // UNIT TESTS - Assignment Logic
    // ==========================================

    /**
     * Test ToShippedTransition validates user assignment
     */
    public function test_shipped_transition_validates_users(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-UNIT-ASSIGN-001',
            'customer_name' => 'Unit Test',
            'total_amount' => 100.00,
            'processed_at' => now(),
        ]);

        $transition = new ToShippedTransition($order, [
            'assigned_users' => [],
            'tracking_number' => 'TRK',
            'carrier' => 'ups',
        ]);

        // Without users assigned, handle() should throw
        $this->expectException(RuntimeException::class);
        $transition->handle();
    }

    /**
     * Test ToShippedTransition accepts user IDs
     */
    public function test_shipped_transition_accepts_user_ids(): void
    {
        $user = $this->createTestUser(['name' => 'Accepted', 'email' => 'accepted@test.com']);

        $order = Order::create([
            'order_number' => 'ORD-UNIT-ASSIGN-002',
            'customer_name' => 'Unit Test',
            'total_amount' => 100.00,
            'processed_at' => now(),
        ]);

        $order->state = new ProcessingState($order);
        $order->save();

        $transition = new ToShippedTransition($order, [
            'assigned_users' => [$user->id],
            'tracking_number' => 'TRK-UNIT',
            'carrier' => 'fedex',
        ]);

        $result = $transition->handle();

        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertInstanceOf(Order::class, $result);
        $this->assertTrue($result->isAssignedTo($user, 'secondary'));
    }

    /**
     * Test complete workflow with assignments at each step
     *
     * @throws CouldNotPerformTransition
     */
    public function test_complete_workflow_with_assignments(): void
    {
        $processor = $this->createTestUser(['name' => 'Processor', 'email' => 'proc@test.com']);
        $shipper = $this->createTestUser(['name' => 'Shipper', 'email' => 'ship@test.com']);
        $deliverer = $this->createTestUser(['name' => 'Deliverer', 'email' => 'deliver@test.com']);

        $order = Order::create([
            'order_number' => 'ORD-COMPLETE-001',
            'customer_name' => 'Complete Test',
            'total_amount' => 1000.00,
        ]);

        // Set initial state
        $order->state = new PendingState($order);
        $order->save();

        // Step 1: Pending -> Processing
        $order->assignTo($processor, 'primary');
        $order->state->transitionTo(ProcessingState::class, [
            'processing_notes' => 'Complete workflow test',
        ]);
        $order->refresh();

        $this->assertInstanceOf(ProcessingState::class, $order->state);
        $this->assertTrue($order->isAssignedTo($processor, 'primary'));

        // Step 2: Processing -> Shipped
        $order->state->transitionTo(ShippedState::class, [
            'assigned_users' => [$shipper->id],
            'tracking_number' => 'TRK-COMPLETE',
            'carrier' => 'ups',
        ]);
        $order->refresh();

        $this->assertInstanceOf(ShippedState::class, $order->state);
        $this->assertTrue($order->isAssignedTo($shipper, 'secondary'));

        // Step 3: Shipped -> Delivered
        $order->assignTo($deliverer, 'viewer');
        $order->state->transitionTo(DeliveredState::class, [
            'signature_received' => true,
            'delivery_notes' => 'Delivered successfully',
        ]);
        $order->refresh();

        $this->assertInstanceOf(DeliveredState::class, $order->state);

        // All assignments should still exist
        $this->assertTrue($order->isAssignedTo($processor, 'primary'));
        $this->assertTrue($order->isAssignedTo($shipper, 'secondary'));
        $this->assertTrue($order->isAssignedTo($deliverer, 'viewer'));

        // Verify all timestamps are set
        $this->assertNotNull($order->processed_at);
        $this->assertNotNull($order->shipped_at);
        $this->assertNotNull($order->delivered_at);
    }
}
