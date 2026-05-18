<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Concerns;

use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\DeliveredState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ShippedState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Tests for state sort order functionality.
 *
 * The HasStateSortOrder trait provides getStatesSortOrder() and getSortOrderValue().
 * Individual state classes define getSortOrder() as a static method.
 */
class HasStateSortOrderTest extends TestCase
{
    private function createOrderInState(string $stateClass, array $attributes = []): Order
    {
        $order = Order::create(array_merge([
            'order_number' => 'ORD-SORT-'.uniqid(),
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ], $attributes));
        $order->state = new $stateClass($order);
        $order->save();
        $order->refresh();

        return $order;
    }

    public function test_pending_state_has_sort_order(): void
    {
        $this->assertEquals(10, PendingState::getSortOrder());
    }

    public function test_processing_state_has_sort_order(): void
    {
        $this->assertEquals(20, ProcessingState::getSortOrder());
    }

    public function test_shipped_state_has_sort_order(): void
    {
        $this->assertEquals(30, ShippedState::getSortOrder());
    }

    public function test_delivered_state_has_sort_order(): void
    {
        $this->assertEquals(40, DeliveredState::getSortOrder());
    }

    public function test_sort_order_increases_through_workflow(): void
    {
        $this->assertLessThan(ProcessingState::getSortOrder(), PendingState::getSortOrder());
        $this->assertLessThan(ShippedState::getSortOrder(), ProcessingState::getSortOrder());
        $this->assertLessThan(DeliveredState::getSortOrder(), ShippedState::getSortOrder());
    }
}
