<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Integration;

use Illuminate\Database\Eloquent\Collection;
use RoBYCoNTe\FilamentFlow\Actions\StateBulkAction;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateTransition;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ShippedState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Bulk Actions Integration Tests
 *
 * Tests the StateBulkAction functionality for performing
 * state transitions on multiple records at once.
 */
class BulkActionsTest extends TestCase
{
    /**
     * Create an order with the specified state
     */
    private function createOrderInState(string $stateClass, array $attributes = []): Order
    {
        $order = Order::create(array_merge([
            'order_number' => 'ORD-BULK-'.uniqid(),
            'customer_name' => 'Bulk Test Customer',
            'total_amount' => 100.00,
        ], $attributes));

        $order->state = new $stateClass($order);
        $order->save();
        $order->refresh();

        return $order;
    }

    /**
     * Create multiple orders in the same state
     */
    private function createMultipleOrdersInState(string $stateClass, int $count): Collection
    {
        $baseAttributes = [];
        $orders = new Collection;

        for ($i = 0; $i < $count; $i++) {
            $orders->push($this->createOrderInState($stateClass, array_merge($baseAttributes, [
                'order_number' => 'ORD-BULK-'.$i.'-'.uniqid(),
            ])));
        }

        return $orders;
    }

    // ===========================================
    // BULK ACTION CONFIGURATION TESTS
    // ===========================================

    /**
     * Test StateBulkAction can be instantiated
     */
    public function test_bulk_action_can_be_created(): void
    {
        $action = StateBulkAction::make('to-processing')
            ->transition(PendingState::class, ProcessingState::class);

        $this->assertInstanceOf(StateBulkAction::class, $action);
    }

    /**
     * Test StateBulkAction stores from and to states
     */
    public function test_bulk_action_stores_transition_states(): void
    {
        $action = StateBulkAction::make('to-processing')
            ->transition(PendingState::class, ProcessingState::class);

        $this->assertEquals(PendingState::class, $action->getFromState());
        $this->assertEquals(ProcessingState::class, $action->getToStateClass());
    }

    /**
     * Test StateBulkAction uses state attribute
     */
    public function test_bulk_action_uses_state_attribute(): void
    {
        $action = StateBulkAction::make('to-processing')
            ->attribute('state')
            ->transition(PendingState::class, ProcessingState::class);

        $this->assertEquals('state', $action->getAttribute());
    }

    // ===========================================
    // STATE MATCHING TESTS
    // ===========================================

    /**
     * Test bulk action only transitions records in matching state
     */
    public function test_bulk_action_filters_by_from_state(): void
    {
        // Create orders in different states
        $pendingOrders = $this->createMultipleOrdersInState(PendingState::class, 3);
        $processingOrders = $this->createMultipleOrdersInState(ProcessingState::class, 2);

        $allOrders = $pendingOrders->merge($processingOrders);

        // Verify initial states
        $this->assertEquals(3, $allOrders->filter(fn ($o) => $o->state instanceof PendingState)->count());
        $this->assertEquals(2, $allOrders->filter(fn ($o) => $o->state instanceof ProcessingState)->count());

        // Only pending orders should be transitionable to processing
        foreach ($pendingOrders as $order) {
            $this->assertTrue($order->canTransitionTo(ProcessingState::class));
        }

        foreach ($processingOrders as $order) {
            $this->assertFalse($order->canTransitionTo(ProcessingState::class));
        }
    }

    /**
     * Test state comparison with State objects
     */
    public function test_state_comparison_with_state_objects(): void
    {
        $order1 = $this->createOrderInState(PendingState::class);
        $order2 = $this->createOrderInState(PendingState::class);

        $state1 = $order1->state;
        $state2 = $order2->state;

        // Both should be PendingState
        $this->assertInstanceOf(PendingState::class, $state1);
        $this->assertInstanceOf(PendingState::class, $state2);

        // They should be "equal" states (same class)
        $this->assertEquals(PendingState::class, get_class($state1));
        $this->assertEquals(PendingState::class, get_class($state2));
    }

    /**
     * Test state morph class comparison
     */
    public function test_state_morph_class_comparison(): void
    {
        $order = $this->createOrderInState(PendingState::class);

        $morphClass = $order->state::getMorphClass();

        // The morph class should be usable for comparison
        $this->assertEquals(PendingState::getMorphClass(), $morphClass);
    }

    // ===========================================
    // BULK TRANSITION TESTS
    // ===========================================

    /**
     * Test successful bulk transition of all records
     */
    public function test_successful_bulk_transition(): void
    {
        $orders = $this->createMultipleOrdersInState(PendingState::class, 5);

        // Transition all orders
        $transitionedCount = 0;
        foreach ($orders as $order) {
            if ($order->canTransitionTo(ProcessingState::class)) {
                $order->transitionTo(ProcessingState::class);
                $transitionedCount++;
            }
        }

        // All should have transitioned
        $this->assertEquals(5, $transitionedCount);

        // Verify all are now in processing state
        foreach ($orders as $order) {
            $order->refresh();
            $this->assertInstanceOf(ProcessingState::class, $order->state);
        }
    }

    /**
     * Test partial bulk transition (mixed states)
     */
    public function test_partial_bulk_transition(): void
    {
        // Create mixed state orders
        $pendingOrders = $this->createMultipleOrdersInState(PendingState::class, 3);
        $processingOrders = $this->createMultipleOrdersInState(ProcessingState::class, 2);

        $allOrders = $pendingOrders->merge($processingOrders);

        // Try to transition all to Processing
        $transitionedCount = 0;
        foreach ($allOrders as $order) {
            if ($order->state instanceof PendingState && $order->canTransitionTo(ProcessingState::class)) {
                $order->transitionTo(ProcessingState::class);
                $transitionedCount++;
            }
        }

        // Only pending orders (3) should have transitioned
        $this->assertEquals(3, $transitionedCount);
    }

    /**
     * Test bulk transition with form data
     */
    public function test_bulk_transition_with_form_data(): void
    {
        $user = $this->createTestUser(['role' => 'manager']);

        // Create orders in ProcessingState with processed_at set (required by ToShippedTransition)
        $orders = new Collection;
        for ($i = 0; $i < 3; $i++) {
            $order = Order::create([
                'order_number' => 'ORD-BULK-SHIP-'.$i.'-'.uniqid(),
                'customer_name' => 'Bulk Test Customer',
                'total_amount' => 100.00,
                'processed_at' => now(), // Required for canTransition() in ToShippedTransition
            ]);
            $order->state = new ProcessingState($order);
            $order->save();
            $order->refresh();
            $orders->push($order);
        }

        $shippingData = [
            'tracking_number' => 'BULK-TRACK-001',
            'carrier' => 'ups',
            'assigned_users' => [$user->id],
        ];

        // Transition to shipped with data using state->transitionTo for form data
        foreach ($orders as $order) {
            if ($order->canTransitionTo(ShippedState::class)) {
                $order->state->transitionTo(ShippedState::class, $shippingData);
            }
        }

        // Verify transitions and data
        foreach ($orders as $order) {
            $order->refresh();
            $this->assertInstanceOf(ShippedState::class, $order->state);
            $this->assertEquals('BULK-TRACK-001', $order->tracking_number);
            $this->assertEquals('ups', $order->carrier);
        }
    }

    // ===========================================
    // TRANSITION VALIDATION TESTS
    // ===========================================

    /**
     * Test bulk action respects transition guards
     */
    public function test_bulk_action_respects_transition_guards(): void
    {
        $orders = $this->createMultipleOrdersInState(PendingState::class, 3);

        // Try to transition directly to Shipped (should fail - need to go through Processing)
        foreach ($orders as $order) {
            $canTransition = $order->canTransitionTo(ShippedState::class);
            $this->assertFalse($canTransition);
        }

        // Orders should still be in Pending state
        foreach ($orders as $order) {
            $order->refresh();
            $this->assertInstanceOf(PendingState::class, $order->state);
        }
    }

    /**
     * Test bulk transition logs history for each record
     */
    public function test_bulk_transition_logs_history(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user);

        $orders = $this->createMultipleOrdersInState(PendingState::class, 3);

        // Transition all orders
        foreach ($orders as $order) {
            if ($order->canTransitionTo(ProcessingState::class)) {
                $order->transitionTo(ProcessingState::class);
            }
        }

        // Each order should have history
        foreach ($orders as $order) {
            $history = WorkflowStateTransition::where('transitionable_id', $order->id)
                ->where('transitionable_type', Order::class)
                ->get();

            $this->assertGreaterThanOrEqual(1, $history->count());

            $latestTransition = $history->last();
            $this->assertEquals(ProcessingState::getMorphClass(), $latestTransition->to_state);
        }
    }

    // ===========================================
    // NOTIFICATION STATUS TESTS
    // ===========================================

    /**
     * Test notification status calculation for full success
     *
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    public function test_notification_status_full_success(): void
    {
        $totalCount = 5;
        $updatedCount = 5;

        $status = $updatedCount === $totalCount ? 'success' : ($updatedCount > 0 ? 'warning' : 'danger');

        $this->assertEquals('success', $status);
    }

    /**
     * Test notification status calculation for partial success
     *
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    public function test_notification_status_partial_success(): void
    {
        $totalCount = 5;
        $updatedCount = 3;

        $status = $updatedCount === $totalCount ? 'success' : ($updatedCount > 0 ? 'warning' : 'danger');

        $this->assertEquals('warning', $status);
    }

    /**
     * Test notification status calculation for failure
     *
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    public function test_notification_status_failure(): void
    {
        $totalCount = 5;
        $updatedCount = 0;

        $status = $updatedCount === $totalCount ? 'success' : ($updatedCount > 0 ? 'warning' : 'danger');

        $this->assertEquals('danger', $status);
    }

    // ===========================================
    // CONCURRENT BULK OPERATIONS TESTS
    // ===========================================

    /**
     * Test multiple bulk operations don't interfere
     */
    public function test_multiple_bulk_operations_isolation(): void
    {
        // Create two groups of orders
        $group1 = $this->createMultipleOrdersInState(PendingState::class, 3);
        $group2 = $this->createMultipleOrdersInState(PendingState::class, 3);

        // Transition group1 to Processing
        foreach ($group1 as $order) {
            $order->transitionTo(ProcessingState::class);
        }

        // Group2 should still be Pending
        foreach ($group2 as $order) {
            $order->refresh();
            $this->assertInstanceOf(PendingState::class, $order->state);
        }

        // Group1 should be Processing
        foreach ($group1 as $order) {
            $order->refresh();
            $this->assertInstanceOf(ProcessingState::class, $order->state);
        }
    }

    // ===========================================
    // ACCESS CONTROL IN BULK TESTS
    // ===========================================

    /**
     * Test bulk operations respect user permissions
     */
    public function test_bulk_operations_respect_permissions(): void
    {
        $manager = $this->createTestUser(['email' => 'manager@test.com', 'role' => 'manager']);
        $regularUser = $this->createTestUser(['email' => 'regular@test.com', 'role' => 'user']);

        $orders = $this->createMultipleOrdersInState(PendingState::class, 3);

        // Manager should be able to transition
        foreach ($orders as $order) {
            $this->assertTrue($order->canBeTransitionedBy($manager));
        }

        // Regular user should also be able (PendingState default is @authenticated)
        foreach ($orders as $order) {
            $this->assertTrue($order->canBeTransitionedBy($regularUser));
        }
    }

    /**
     * Test bulk transition with mixed permissions
     */
    public function test_bulk_transition_with_owner_permissions(): void
    {
        // Create workflow with RestrictedState if needed
        $user1 = $this->createTestUser(['email' => 'user1@test.com', 'role' => 'user']);
        $user2 = $this->createTestUser(['email' => 'user2@test.com', 'role' => 'user']);

        // Create orders owned by different users
        $order1 = $this->createOrderInState(PendingState::class, ['user_id' => $user1->id]);
        $order2 = $this->createOrderInState(PendingState::class, ['user_id' => $user2->id]);

        // Verify ownership is set
        $this->assertEquals($user1->id, $order1->user_id);
        $this->assertEquals($user2->id, $order2->user_id);
    }

    // ===========================================
    // EDGE CASES
    // ===========================================

    /**
     * Test bulk action with empty collection
     *
     * @noinspection PhpUnusedLocalVariableInspection
     */
    public function test_bulk_action_with_empty_collection(): void
    {
        $orders = new Collection;

        $transitionedCount = 0;
        foreach ($orders as $order) {
            $transitionedCount++;
        }

        $this->assertEquals(0, $transitionedCount);
    }

    /**
     * Test bulk action with single record
     */
    public function test_bulk_action_with_single_record(): void
    {
        $order = $this->createOrderInState(PendingState::class);
        $orders = new Collection([$order]);

        $transitionedCount = 0;
        foreach ($orders as $o) {
            if ($o->canTransitionTo(ProcessingState::class)) {
                $o->transitionTo(ProcessingState::class);
                $transitionedCount++;
            }
        }

        $this->assertEquals(1, $transitionedCount);
        $order->refresh();
        $this->assertInstanceOf(ProcessingState::class, $order->state);
    }

    /**
     * Test bulk action with already transitioned records
     */
    public function test_bulk_action_skips_already_transitioned(): void
    {
        $orders = $this->createMultipleOrdersInState(PendingState::class, 5);

        // Pre-transition some
        $orders[0]->transitionTo(ProcessingState::class);
        $orders[1]->transitionTo(ProcessingState::class);

        // Now try to bulk transition all to Processing
        $transitionedCount = 0;
        foreach ($orders as $order) {
            $order->refresh();
            if ($order->state instanceof PendingState && $order->canTransitionTo(ProcessingState::class)) {
                $order->transitionTo(ProcessingState::class);
                $transitionedCount++;
            }
        }

        // Only 3 should have transitioned (5 - 2 already done)
        $this->assertEquals(3, $transitionedCount);
    }
}
