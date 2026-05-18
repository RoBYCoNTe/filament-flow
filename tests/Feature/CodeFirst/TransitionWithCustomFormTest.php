<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\CodeFirst;

use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ShippedState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Transitions\ToProcessingTransition;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Transitions\ToShippedTransition;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

/**
 * Integration Tests: Transitions with Custom Forms
 *
 * These tests verify that:
 * - Transition classes with form() method work correctly
 * - Form data is passed to the transition handle() method
 * - Data from transition form is saved to model attributes
 * - Timestamps are set correctly during transitions
 * - Validation in canTransition() is respected
 */
class TransitionWithCustomFormTest extends TestCase
{
    // ==========================================
    // INTEGRATION TESTS - Full Flow with Database
    // ==========================================

    /**
     * Test that transition with custom form saves data to model
     *
     * @throws CouldNotPerformTransition
     */
    public function test_transition_form_data_saved_to_model(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FORM-001',
            'customer_name' => 'John Doe',
            'total_amount' => 100.00,
        ]);

        // Set initial state manually (Spatie default not auto-applied in test fixture)
        $order->state = new PendingState($order);
        $order->save();
        $order->refresh();

        // Ensure order is in pending state
        $this->assertInstanceOf(PendingState::class, $order->state);

        // Execute transition with form data
        $formData = [
            'processing_notes' => 'Rush order - priority handling required',
            'estimated_delivery' => now()->addDays(3)->format('Y-m-d'),
        ];

        $order->state->transitionTo(ProcessingState::class, $formData);

        // Refresh from database
        $order->refresh();

        // Verify state changed
        $this->assertInstanceOf(ProcessingState::class, $order->state);

        // Verify form data was saved to model
        $this->assertEquals('Rush order - priority handling required', $order->processing_notes);
        $this->assertNotNull($order->estimated_delivery);
        $this->assertEquals(now()->addDays(3)->format('Y-m-d'), $order->estimated_delivery->format('Y-m-d'));
    }

    /**
     * Test that transition sets timestamp
     *
     * @throws CouldNotPerformTransition
     */
    public function test_transition_sets_timestamp(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FORM-002',
            'customer_name' => 'Jane Doe',
            'total_amount' => 150.00,
        ]);

        // Set initial state
        $order->state = new PendingState($order);
        $order->save();

        $this->assertNull($order->processed_at);

        $formData = [
            'processing_notes' => 'Standard processing',
        ];

        $order->state->transitionTo(ProcessingState::class, $formData);
        $order->refresh();

        $this->assertNotNull($order->processed_at);
        $this->assertTrue($order->processed_at->isToday());
    }

    /**
     * Test transition with partial form data
     *
     * @throws CouldNotPerformTransition
     */
    public function test_transition_with_partial_form_data(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FORM-003',
            'customer_name' => 'Bob Smith',
            'total_amount' => 200.00,
        ]);

        // Set initial state
        $order->state = new PendingState($order);
        $order->save();

        // Only provide processing_notes, not estimated_delivery
        $formData = [
            'processing_notes' => 'Partial data test',
        ];

        $order->state->transitionTo(ProcessingState::class, $formData);
        $order->refresh();

        $this->assertInstanceOf(ProcessingState::class, $order->state);
        $this->assertEquals('Partial data test', $order->processing_notes);
        $this->assertNull($order->estimated_delivery);
    }

    /**
     * Test transition without form data still works
     *
     * @throws CouldNotPerformTransition
     */
    public function test_transition_without_form_data(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FORM-004',
            'customer_name' => 'Alice Johnson',
            'total_amount' => 300.00,
        ]);

        // Set initial state
        $order->state = new PendingState($order);
        $order->save();

        // Transition without any form data
        $order->state->transitionTo(ProcessingState::class);
        $order->refresh();

        $this->assertInstanceOf(ProcessingState::class, $order->state);
        $this->assertNull($order->processing_notes);
        $this->assertNotNull($order->processed_at);
    }

    /**
     * Test canTransition validation is respected
     */
    public function test_can_transition_validation_blocks_invalid_transition(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FORM-005',
            'customer_name' => 'Charlie Brown',
            'total_amount' => 0, // Zero amount - should block ToProcessingTransition
        ]);

        // ToProcessingTransition.canTransition() returns false when total_amount <= 0
        $transition = new ToProcessingTransition($order, []);

        $this->assertFalse($transition->canTransition());
    }

    /**
     * Test canTransition validation allows valid transition
     */
    public function test_can_transition_validation_allows_valid_transition(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FORM-006',
            'customer_name' => 'David Lee',
            'total_amount' => 100.00,
        ]);

        $transition = new ToProcessingTransition($order, []);

        $this->assertTrue($transition->canTransition());
    }

    /**
     * Test full workflow with form data at each step
     *
     * @throws CouldNotPerformTransition
     */
    public function test_full_workflow_with_form_data(): void
    {
        $user = $this->createTestUser(['name' => 'Shipper', 'email' => 'shipper@test.com']);

        $order = Order::create([
            'order_number' => 'ORD-FORM-007',
            'customer_name' => 'Eve Martin',
            'total_amount' => 500.00,
        ]);

        // Set initial state
        $order->state = new PendingState($order);
        $order->save();

        // Step 1: Pending -> Processing with notes
        $order->state->transitionTo(ProcessingState::class, [
            'processing_notes' => 'High priority order',
            'estimated_delivery' => now()->addDays(5)->format('Y-m-d'),
        ]);
        $order->refresh();

        $this->assertInstanceOf(ProcessingState::class, $order->state);
        $this->assertEquals('High priority order', $order->processing_notes);

        // Step 2: Processing -> Shipped with tracking info and user assignment
        $order->state->transitionTo(ShippedState::class, [
            'assigned_users' => [$user->id],
            'tracking_number' => 'TRK123456789',
            'carrier' => 'fedex',
            'shipping_notes' => 'Handle with care',
        ]);
        $order->refresh();

        $this->assertInstanceOf(ShippedState::class, $order->state);
        $this->assertEquals('TRK123456789', $order->tracking_number);
        $this->assertEquals('fedex', $order->carrier);
        $this->assertEquals('Handle with care', $order->shipping_notes);
        $this->assertNotNull($order->shipped_at);

        // Verify user assignment
        $this->assertTrue($order->isAssignedTo($user, 'secondary'));
    }

    /**
     * Test transition form data persists across database operations
     *
     * @throws CouldNotPerformTransition
     */
    public function test_form_data_persists_in_database(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FORM-008',
            'customer_name' => 'Frank Miller',
            'total_amount' => 250.00,
        ]);

        // Set initial state
        $order->state = new PendingState($order);
        $order->save();

        $orderId = $order->id;

        $order->state->transitionTo(ProcessingState::class, [
            'processing_notes' => 'Persistence test notes',
            'estimated_delivery' => '2026-02-15',
        ]);

        // Clear any cached data by creating new query
        $freshOrder = Order::find($orderId);

        $this->assertEquals('Persistence test notes', $freshOrder->processing_notes);
        $this->assertEquals('2026-02-15', $freshOrder->estimated_delivery->format('Y-m-d'));
    }

    // ==========================================
    // UNIT TESTS - Transition Class Logic
    // ==========================================

    /**
     * Test transition class has form method
     */
    public function test_transition_class_has_form_method(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-UNIT-001',
            'customer_name' => 'Unit Test',
            'total_amount' => 100.00,
        ]);

        $transition = new ToProcessingTransition($order, []);

        $this->assertTrue(method_exists($transition, 'form'));
    }

    /**
     * Test transition form returns array of Filament components
     */
    public function test_transition_form_returns_filament_components(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-UNIT-002',
            'customer_name' => 'Unit Test',
            'total_amount' => 100.00,
        ]);

        $transition = new ToProcessingTransition($order, []);
        $form = $transition->form();

        $this->assertIsArray($form);
        $this->assertNotEmpty($form);

        // Verify components are Filament form components
        foreach ($form as $component) {
            $this->assertIsObject($component);
            $this->assertTrue(method_exists($component, 'getName'));
        }
    }

    /**
     * Test transition form field names match expected
     */
    public function test_transition_form_has_expected_fields(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-UNIT-003',
            'customer_name' => 'Unit Test',
            'total_amount' => 100.00,
        ]);

        $transition = new ToProcessingTransition($order, []);
        $form = $transition->form();

        $fieldNames = array_map(fn ($component) => $component->getName(), $form);

        $this->assertContains('processing_notes', $fieldNames);
        $this->assertContains('estimated_delivery', $fieldNames);
    }

    /**
     * Test transition requiresConfirmation method
     */
    public function test_transition_requires_confirmation(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-UNIT-004',
            'customer_name' => 'Unit Test',
            'total_amount' => 100.00,
        ]);

        $transition = new ToProcessingTransition($order, []);

        $this->assertTrue($transition->requiresConfirmation());
    }

    /**
     * Test ToShippedTransition form has user selection
     */
    public function test_shipped_transition_has_user_selection(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-UNIT-005',
            'customer_name' => 'Unit Test',
            'total_amount' => 100.00,
            'processed_at' => now(),
        ]);

        $transition = new ToShippedTransition($order, []);
        $form = $transition->form();

        $fieldNames = array_map(fn ($component) => $component->getName(), $form);

        $this->assertContains('assigned_users', $fieldNames);
        $this->assertContains('tracking_number', $fieldNames);
        $this->assertContains('carrier', $fieldNames);
    }

    /**
     * Test shipped transition requires processed_at to be set
     */
    public function test_shipped_transition_requires_processed_at(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-UNIT-006',
            'customer_name' => 'Unit Test',
            'total_amount' => 100.00,
            // processed_at not set
        ]);

        $transition = new ToShippedTransition($order, []);

        $this->assertFalse($transition->canTransition());
    }

    /**
     * Test shipped transition allows when processed_at is set
     */
    public function test_shipped_transition_allows_when_processed(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-UNIT-007',
            'customer_name' => 'Unit Test',
            'total_amount' => 100.00,
            'processed_at' => now(),
        ]);

        $transition = new ToShippedTransition($order, []);

        $this->assertTrue($transition->canTransition());
    }
}
