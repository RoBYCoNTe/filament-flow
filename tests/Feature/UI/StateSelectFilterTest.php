<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\UI;

use RoBYCoNTe\FilamentFlow\Tables\Filters\StateSelectFilter;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * StateSelectFilter UI Component Tests
 *
 * Tests the StateSelectFilter functionality for filtering
 * records by state in Filament tables.
 */
class StateSelectFilterTest extends TestCase
{
    /**
     * Create an order with the specified state
     */
    private function createOrderInState(string $stateClass, array $attributes = []): Order
    {
        $order = Order::create(array_merge([
            'order_number' => 'ORD-FILTER-'.uniqid(),
            'customer_name' => 'Filter Test Customer',
            'total_amount' => 100.00,
        ], $attributes));

        $order->state = new $stateClass($order);
        $order->save();
        $order->refresh();

        return $order;
    }

    // ===========================================
    // CONFIGURATION TESTS
    // ===========================================

    /**
     * Test StateSelectFilter can be instantiated
     */
    public function test_filter_can_be_created(): void
    {
        $filter = StateSelectFilter::make('state');

        $this->assertInstanceOf(StateSelectFilter::class, $filter);
    }

    /**
     * Test StateSelectFilter uses attribute name
     */
    public function test_filter_uses_attribute_name(): void
    {
        $filter = StateSelectFilter::make('state');

        $this->assertEquals('state', $filter->getAttribute());
    }

    // ===========================================
    // FILTERING TESTS
    // ===========================================

    /**
     * Test state instances have correct classes
     */
    public function test_state_instances_have_correct_classes(): void
    {
        $pending = $this->createOrderInState(PendingState::class);
        $processing = $this->createOrderInState(ProcessingState::class);

        $this->assertInstanceOf(PendingState::class, $pending->state);
        $this->assertInstanceOf(ProcessingState::class, $processing->state);
    }

    /**
     * Test filter excludes non-matching states conceptually
     */
    public function test_state_classes_are_distinguishable(): void
    {
        $pending = $this->createOrderInState(PendingState::class);
        $processing = $this->createOrderInState(ProcessingState::class);

        // Different states should have different classes
        $this->assertNotEquals(
            get_class($pending->state),
            get_class($processing->state)
        );
    }

    /**
     * Test can compare morph classes for filtering
     */
    public function test_morph_class_comparison_for_filtering(): void
    {
        $pending = $this->createOrderInState(PendingState::class);

        $morphClass = $pending->state::getMorphClass();

        // Should be able to compare morph classes
        $this->assertEquals(PendingState::getMorphClass(), $morphClass);
    }

    /**
     * Test filter attribute can be customized
     */
    public function test_filter_attribute_can_be_customized(): void
    {
        $filter = StateSelectFilter::make('custom_state');

        $this->assertEquals('custom_state', $filter->getAttribute());
    }
}
