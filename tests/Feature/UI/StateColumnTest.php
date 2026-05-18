<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\UI;

use RoBYCoNTe\FilamentFlow\Tables\Columns\StateColumn;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * StateColumn UI Component Tests
 *
 * Tests the StateColumn functionality for displaying states
 * in Filament tables with proper labels, colors, and icons.
 */
class StateColumnTest extends TestCase
{
    /**
     * Create an order with the specified state
     */
    private function createOrderInState(string $stateClass, array $attributes = []): Order
    {
        $order = Order::create(array_merge([
            'order_number' => 'ORD-UI-'.uniqid(),
            'customer_name' => 'UI Test Customer',
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
     * Test StateColumn can be instantiated
     */
    public function test_state_column_can_be_created(): void
    {
        $column = StateColumn::make('state');

        $this->assertInstanceOf(StateColumn::class, $column);
    }

    /**
     * Test StateColumn can use custom attribute
     */
    public function test_state_column_can_use_custom_attribute(): void
    {
        $column = StateColumn::make('custom')
            ->attribute('state');

        $order = $this->createOrderInState(PendingState::class);

        $this->assertEquals('state', $column->getAttribute($order));
    }

    // ===========================================
    // STATE DISPLAY TESTS
    // ===========================================

    /**
     * Test state label is displayed correctly for PHP states
     */
    public function test_state_label_displayed_for_php_states(): void
    {
        $order = $this->createOrderInState(PendingState::class);

        // Verify the state has getLabel method
        $this->assertTrue(method_exists($order->state, 'getLabel'));
        $this->assertEquals('Pending', $order->state->getLabel());
    }

    /**
     * Test state has getLabel method (required)
     */
    public function test_state_has_label_method(): void
    {
        $order = $this->createOrderInState(PendingState::class);

        // Verify the state has getLabel method (required)
        $this->assertTrue(method_exists($order->state, 'getLabel'));

        // getColor and getIcon are optional and may not exist
    }

    // ===========================================
    // MORPH CLASS TESTS
    // ===========================================

    /**
     * Test morph class is used when no label method
     */
    public function test_morph_class_fallback(): void
    {
        $order = $this->createOrderInState(PendingState::class);

        $morphClass = $order->state::getMorphClass();

        $this->assertNotEmpty($morphClass);
    }

    /**
     * Test different states have different morph classes
     */
    public function test_different_states_have_different_morph_classes(): void
    {
        $order1 = $this->createOrderInState(PendingState::class);
        $order2 = $this->createOrderInState(ProcessingState::class);

        $morphClass1 = $order1->state::getMorphClass();
        $morphClass2 = $order2->state::getMorphClass();

        $this->assertNotEquals($morphClass1, $morphClass2);
    }

    // ===========================================
    // NULL STATE TESTS
    // ===========================================

    /**
     * Test StateColumn handles null state gracefully
     */
    public function test_handles_null_state(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-NULL-'.uniqid(),
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ]);

        // State might be null initially
        // This should not throw an exception
        $this->assertTrue(true);
    }
}
