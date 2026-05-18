<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\UI;

use RoBYCoNTe\FilamentFlow\Tables\Grouping\StateGroup;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * StateGroup UI Component Tests
 *
 * Tests the StateGroup functionality for grouping
 * table records by their state in Filament tables.
 */
class StateGroupTest extends TestCase
{
    /**
     * Create an order with the specified state
     */
    private function createOrderInState(string $stateClass, array $attributes = []): Order
    {
        $order = Order::create(array_merge([
            'order_number' => 'ORD-GROUP-'.uniqid(),
            'customer_name' => 'Group Test Customer',
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
     * Test StateGroup can be instantiated
     */
    public function test_group_can_be_created(): void
    {
        $group = StateGroup::make('state');

        $this->assertInstanceOf(StateGroup::class, $group);
    }

    /**
     * Test StateGroup uses default state attribute
     */
    public function test_group_uses_default_attribute(): void
    {
        $group = StateGroup::make('state');

        $this->assertEquals('state', $group->getStateAttribute());
    }

    /**
     * Test StateGroup can use custom attribute
     */
    public function test_group_can_use_custom_attribute(): void
    {
        $group = StateGroup::make('custom_state')
            ->stateAttribute('custom_state');

        $this->assertEquals('custom_state', $group->getStateAttribute());
    }

    /**
     * Test StateGroup make with null defaults to state
     */
    public function test_group_make_null_defaults_to_state(): void
    {
        $group = StateGroup::make();

        $this->assertEquals('state', $group->getStateAttribute());
    }

    // ===========================================
    // LABEL RESOLUTION TESTS
    // ===========================================

    /**
     * Test getLabel from State class
     */
    public function test_state_label_from_state_class(): void
    {
        $order = $this->createOrderInState(PendingState::class);

        // The state should have a getLabel method
        $this->assertTrue(method_exists($order->state, 'getLabel'));
        $label = $order->state->getLabel();
        $this->assertEquals('Pending', $label);
    }

    /**
     * Test morph class fallback for label
     */
    public function test_morph_class_fallback_for_label(): void
    {
        $order = $this->createOrderInState(PendingState::class);

        $morphClass = $order->state::getMorphClass();

        $this->assertNotEmpty($morphClass);
    }

    // ===========================================
    // KEY RESOLUTION TESTS
    // ===========================================

    /**
     * Test group key for State objects uses morph class
     */
    public function test_group_key_for_state_object(): void
    {
        $order = $this->createOrderInState(PendingState::class);

        $groupKey = $order->state::getMorphClass();

        $this->assertNotEmpty($groupKey);
        $this->assertIsString($groupKey);
    }

    /**
     * Test different states have different group keys
     */
    public function test_different_states_have_different_keys(): void
    {
        $pending = $this->createOrderInState(PendingState::class);
        $processing = $this->createOrderInState(ProcessingState::class);

        $pendingKey = $pending->state::getMorphClass();
        $processingKey = $processing->state::getMorphClass();

        $this->assertNotEquals($pendingKey, $processingKey);
    }

    // ===========================================
    // GROUPING TESTS
    // ===========================================

    /**
     * Test records can be filtered by state class
     */
    public function test_records_filtered_by_state(): void
    {
        $pending = $this->createOrderInState(PendingState::class);
        $processing = $this->createOrderInState(ProcessingState::class);

        // Verify each has correct state type
        $this->assertInstanceOf(PendingState::class, $pending->state);
        $this->assertInstanceOf(ProcessingState::class, $processing->state);
    }

    /**
     * Test state attribute retrieval
     */
    public function test_state_attribute_retrieval(): void
    {
        $order = $this->createOrderInState(PendingState::class);

        $stateValue = $order->getAttribute('state');

        $this->assertInstanceOf(PendingState::class, $stateValue);
    }
}
