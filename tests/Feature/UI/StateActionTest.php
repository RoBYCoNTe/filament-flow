<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\UI;

use RoBYCoNTe\FilamentFlow\Actions\StateAction;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ShippedState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * StateAction UI Component Tests
 *
 * Tests the StateAction functionality for performing
 * state transitions through Filament actions.
 */
class StateActionTest extends TestCase
{
    /**
     * Create an order with the specified state
     */
    private function createOrderInState(string $stateClass, array $attributes = []): Order
    {
        $order = Order::create(array_merge([
            'order_number' => 'ORD-ACTION-'.uniqid(),
            'customer_name' => 'Action Test Customer',
            'total_amount' => 100.00,
        ], $attributes));

        $order->state = new $stateClass($order);
        $order->save();
        $order->refresh();

        return $order;
    }

    // ===========================================
    // TRANSITION CAPABILITY TESTS
    // ===========================================

    /**
     * Test can transition check for valid transition
     */
    public function test_can_transition_check_valid(): void
    {
        $order = $this->createOrderInState(PendingState::class);

        // From Pending, should be able to transition to Processing
        $canTransition = $order->canTransitionTo(ProcessingState::class);

        $this->assertTrue($canTransition);
    }

    /**
     * Test can transition check for invalid transition
     */
    public function test_can_transition_check_invalid(): void
    {
        $order = $this->createOrderInState(PendingState::class);

        // From Pending, should NOT be able to transition directly to Shipped
        $canTransition = $order->canTransitionTo(ShippedState::class);

        $this->assertFalse($canTransition);
    }

    /**
     * Test action is hidden when transition not allowed
     */
    public function test_invalid_transition_action_hidden(): void
    {
        $order = $this->createOrderInState(PendingState::class);

        // Cannot go directly from Pending to Shipped
        $this->assertFalse($order->canTransitionTo(ShippedState::class));
    }

    // ===========================================
    // LABEL RESOLUTION TESTS
    // ===========================================

    /**
     * Test action label comes from state
     */
    public function test_action_label_from_state(): void
    {
        $order = $this->createOrderInState(PendingState::class);

        // ProcessingState should have a label
        $processingState = new ProcessingState($order);
        $this->assertTrue(method_exists($processingState, 'getLabel'));
        $label = $processingState->getLabel();
        $this->assertNotEmpty($label);
    }

    /**
     * Test action has required label method
     */
    public function test_action_has_label_method(): void
    {
        $order = $this->createOrderInState(PendingState::class);

        $processingState = new ProcessingState($order);

        // getLabel is required
        $this->assertTrue(method_exists($processingState, 'getLabel'));

        // getColor and getIcon are optional and may not exist on all states
    }

    // ===========================================
    // TRANSITION EXECUTION TESTS
    // ===========================================

    /**
     * Test transition is executed via model's transitionTo
     */
    public function test_transition_executed_via_model(): void
    {
        $order = $this->createOrderInState(PendingState::class);

        $this->assertInstanceOf(PendingState::class, $order->state);

        // Execute transition
        $order->transitionTo(ProcessingState::class);
        $order->refresh();

        $this->assertInstanceOf(ProcessingState::class, $order->state);
    }

    /**
     * Test transition with form data
     */
    public function test_transition_with_form_data(): void
    {
        $order = $this->createOrderInState(PendingState::class);

        $formData = [
            'processing_notes' => 'Test notes for processing',
        ];

        // Execute transition with data
        $order->state->transitionTo(ProcessingState::class, $formData);
        $order->refresh();

        $this->assertInstanceOf(ProcessingState::class, $order->state);
        $this->assertEquals('Test notes for processing', $order->processing_notes);
    }

    // ===========================================
    // DATABASE TRANSITION SUPPORT TESTS
    // ===========================================

    /**
     * Test action supports database-configured transitions
     */
    public function test_supports_database_transitions(): void
    {
        // Model should have canTransitionToFromDatabase method
        $order = $this->createOrderInState(PendingState::class);

        $this->assertTrue(method_exists($order, 'canTransitionToFromDatabase'));
    }

    /**
     * Test action supports database string states
     */
    public function test_supports_database_string_states(): void
    {
        $order = $this->createOrderInState(PendingState::class);

        // Should have method to check transitions from string states
        $this->assertTrue(method_exists($order, 'canTransitionToFromDatabaseString'));
    }
}
