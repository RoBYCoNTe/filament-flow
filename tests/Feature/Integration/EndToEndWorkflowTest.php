<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Integration;

use RoBYCoNTe\FilamentFlow\Models\WorkflowStateTransition;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\DeliveredState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ShippedState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;
use Spatie\ModelStates\Exceptions\TransitionNotAllowed;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

/**
 * End-to-end workflow test
 *
 * Tests the complete workflow from start to finish,
 * verifying all transitions, history, and data integrity.
 */
class EndToEndWorkflowTest extends TestCase
{
    private User $shippingUser;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a user for shipping assignments
        $this->shippingUser = $this->createTestUser(['email' => 'shipper@test.com', 'name' => 'Shipping Handler']);
    }

    /**
     * Create an order with the initial PendingState
     */
    private function createOrderWithInitialState(array $attributes = []): Order
    {
        $order = Order::create(array_merge([
            'order_number' => 'ORD-'.uniqid(),
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ], $attributes));

        // Set initial state manually (test fixture doesn't auto-apply Spatie default)
        $order->state = new PendingState($order);
        $order->save();
        $order->refresh();

        return $order;
    }

    /**
     * Get shipping transition data with assigned user
     */
    private function getShippingData(array $overrides = []): array
    {
        return array_merge([
            'tracking_number' => 'TRACK'.uniqid(),
            'carrier' => 'dhl',
            'assigned_users' => [$this->shippingUser->id],
        ], $overrides);
    }

    /**
     * Test complete order workflow from Pending to Delivered
     */
    public function test_complete_order_workflow(): void
    {
        $order = $this->createOrderWithInitialState([
            'order_number' => 'ORD-E2E-001',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'total_amount' => 150.00,
        ]);

        // Verify initial state
        $this->assertInstanceOf(PendingState::class, $order->state);

        // Step 1: Pending → Processing
        $order->state->transitionTo(ProcessingState::class, [
            'processing_notes' => 'Order verified and ready for processing',
            'estimated_delivery' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $order->refresh();
        $this->assertInstanceOf(ProcessingState::class, $order->state);
        $this->assertNotNull($order->processed_at);
        $this->assertEquals('Order verified and ready for processing', $order->processing_notes);

        // Step 2: Processing → Shipped
        $order->state->transitionTo(ShippedState::class, $this->getShippingData([
            'tracking_number' => 'TRACK123456',
            'carrier' => 'dhl',
            'shipping_notes' => 'Shipped via express delivery',
        ]));

        $order->refresh();
        $this->assertInstanceOf(ShippedState::class, $order->state);
        $this->assertNotNull($order->shipped_at);
        $this->assertEquals('TRACK123456', $order->tracking_number);
        $this->assertEquals('dhl', $order->carrier);

        // Step 3: Shipped → Delivered
        $order->state->transitionTo(DeliveredState::class);

        $order->refresh();
        $this->assertInstanceOf(DeliveredState::class, $order->state);
        $this->assertNotNull($order->delivered_at);
    }

    /**
     * Test all transitions are logged in history
     * Note: Uses $order->transitionTo() (model method) to trigger logging
     *
     * @noinspection PhpUnhandledExceptionInspection
     */
    public function test_all_transitions_logged_in_history(): void
    {
        $order = $this->createOrderWithInitialState([
            'order_number' => 'ORD-E2E-002',
            'customer_name' => 'Jane Smith',
            'total_amount' => 250.00,
        ]);

        // Execute full workflow using model's transitionTo (logs history)
        $order->transitionTo(ProcessingState::class, [
            'processing_notes' => 'Processing started',
        ]);
        $order->refresh();

        $order->transitionTo(ShippedState::class, $this->getShippingData([
            'tracking_number' => 'TRACK789',
            'carrier' => 'ups',
        ]));
        $order->refresh();

        $order->transitionTo(DeliveredState::class);

        // Verify history count
        $history = WorkflowStateTransition::where('transitionable_type', Order::class)
            ->where('transitionable_id', $order->id)
            ->orderBy('created_at')
            ->get();

        $this->assertCount(3, $history);

        // Verify each transition in order
        $this->assertEquals(PendingState::class, $history[0]->from_state);
        $this->assertEquals(ProcessingState::class, $history[0]->to_state);

        $this->assertEquals(ProcessingState::class, $history[1]->from_state);
        $this->assertEquals(ShippedState::class, $history[1]->to_state);

        $this->assertEquals(ShippedState::class, $history[2]->from_state);
        $this->assertEquals(DeliveredState::class, $history[2]->to_state);
    }

    /**
     * Test transition history contains notes
     * Note: Uses $order->transitionTo() (model method) to trigger logging
     *
     * @noinspection PhpUnhandledExceptionInspection
     */
    public function test_transition_history_contains_notes(): void
    {
        $order = $this->createOrderWithInitialState([
            'order_number' => 'ORD-E2E-003',
            'customer_name' => 'Bob Wilson',
            'total_amount' => 99.99,
        ]);

        // Use model's transitionTo to log history with notes
        $order->transitionTo(ProcessingState::class, [
            'processing_notes' => 'Urgent order - priority processing',
            'estimated_delivery' => '2026-01-25',
        ]);

        $history = WorkflowStateTransition::where('transitionable_type', Order::class)
            ->where('transitionable_id', $order->id)
            ->first();

        // The ToProcessingTransition generates notes with order info
        $this->assertNotNull($history);
        $this->assertNotNull($history->notes);
        $this->assertStringContainsString('ORD-E2E-003', $history->notes);
        $this->assertStringContainsString('Bob Wilson', $history->notes);
        $this->assertStringContainsString('Urgent order - priority processing', $history->notes);
    }

    /**
     * Test timestamps are set correctly at each transition
     */
    public function test_timestamps_set_correctly(): void
    {
        $order = $this->createOrderWithInitialState([
            'order_number' => 'ORD-E2E-004',
            'customer_name' => 'Alice Brown',
            'total_amount' => 500.00,
        ]);

        // Initial timestamps should be null
        $this->assertNull($order->processed_at);
        $this->assertNull($order->shipped_at);
        $this->assertNull($order->delivered_at);

        // Transition to Processing - use state->transitionTo for form data
        $beforeProcessing = now()->subSecond(); // Allow 1 second tolerance
        $order->state->transitionTo(ProcessingState::class, ['processing_notes' => 'Test']);
        $order->refresh();

        $this->assertNotNull($order->processed_at);
        $this->assertTrue($order->processed_at->gte($beforeProcessing), 'processed_at should be >= beforeProcessing');
        $this->assertNull($order->shipped_at);
        $this->assertNull($order->delivered_at);

        // Transition to Shipped
        $beforeShipping = now()->subSecond();
        $order->state->transitionTo(ShippedState::class, $this->getShippingData([
            'tracking_number' => 'T123',
            'carrier' => 'fedex',
        ]));
        $order->refresh();

        $this->assertNotNull($order->shipped_at);
        $this->assertTrue($order->shipped_at->gte($beforeShipping), 'shipped_at should be >= beforeShipping');
        $this->assertNull($order->delivered_at);

        // Transition to Delivered
        $beforeDelivery = now()->subSecond();
        $order->state->transitionTo(DeliveredState::class);
        $order->refresh();

        $this->assertNotNull($order->delivered_at);
        $this->assertTrue($order->delivered_at->gte($beforeDelivery), 'delivered_at should be >= beforeDelivery');
    }

    /**
     * Test workflow with multiple orders in different states
     */
    public function test_multiple_orders_different_states(): void
    {
        // Create orders in different states
        $pendingOrder = $this->createOrderWithInitialState([
            'order_number' => 'ORD-PENDING',
            'customer_name' => 'Customer 1',
        ]);

        $processingOrder = $this->createOrderWithInitialState([
            'order_number' => 'ORD-PROCESSING',
            'customer_name' => 'Customer 2',
        ]);
        $processingOrder->state->transitionTo(ProcessingState::class, ['processing_notes' => 'Processing']);
        $processingOrder->refresh();

        $shippedOrder = $this->createOrderWithInitialState([
            'order_number' => 'ORD-SHIPPED',
            'customer_name' => 'Customer 3',
        ]);
        $shippedOrder->state->transitionTo(ProcessingState::class, ['processing_notes' => 'Processing']);
        $shippedOrder->refresh();
        $shippedOrder->state->transitionTo(ShippedState::class, $this->getShippingData([
            'tracking_number' => 'T456',
            'carrier' => 'ups',
        ]));
        $shippedOrder->refresh();

        $deliveredOrder = $this->createOrderWithInitialState([
            'order_number' => 'ORD-DELIVERED',
            'customer_name' => 'Customer 4',
        ]);
        $deliveredOrder->state->transitionTo(ProcessingState::class, ['processing_notes' => 'Processing']);
        $deliveredOrder->refresh();
        $deliveredOrder->state->transitionTo(ShippedState::class, $this->getShippingData([
            'tracking_number' => 'T789',
            'carrier' => 'dhl',
        ]));
        $deliveredOrder->refresh();
        $deliveredOrder->state->transitionTo(DeliveredState::class);
        $deliveredOrder->refresh();

        // Verify each order is in correct state
        $this->assertInstanceOf(PendingState::class, $pendingOrder->state);
        $this->assertInstanceOf(ProcessingState::class, $processingOrder->state);
        $this->assertInstanceOf(ShippedState::class, $shippedOrder->state);
        $this->assertInstanceOf(DeliveredState::class, $deliveredOrder->state);

        // Verify query by state works
        $pendingOrders = Order::where('state', PendingState::class)->get();
        $this->assertCount(1, $pendingOrders);
        $this->assertEquals('ORD-PENDING', $pendingOrders->first()->order_number);

        $deliveredOrders = Order::where('state', DeliveredState::class)->get();
        $this->assertCount(1, $deliveredOrders);
        $this->assertEquals('ORD-DELIVERED', $deliveredOrders->first()->order_number);
    }

    /**
     * Test invalid transition is rejected
     */
    public function test_invalid_transition_rejected(): void
    {
        $order = $this->createOrderWithInitialState([
            'order_number' => 'ORD-E2E-005',
            'customer_name' => 'Invalid Test',
            'total_amount' => 50.00,
        ]);

        // Try to skip Processing and go directly to Shipped
        $this->expectException(TransitionNotFound::class);
        $order->state->transitionTo(ShippedState::class);
    }

    /**
     * Test cannot transition from final state
     */
    public function test_cannot_transition_from_delivered(): void
    {
        $order = $this->createOrderWithInitialState([
            'order_number' => 'ORD-E2E-006',
            'customer_name' => 'Final State Test',
            'total_amount' => 75.00,
        ]);

        // Complete workflow
        $order->state->transitionTo(ProcessingState::class, ['processing_notes' => 'Test']);
        $order->refresh();
        $order->state->transitionTo(ShippedState::class, $this->getShippingData([
            'tracking_number' => 'T111',
            'carrier' => 'ups',
        ]));
        $order->refresh();
        $order->state->transitionTo(DeliveredState::class);
        $order->refresh();

        // Try to transition back
        $this->expectException(TransitionNotFound::class);
        $order->state->transitionTo(PendingState::class);
    }

    /**
     * Test transition validation (canTransition check)
     */
    public function test_transition_validation_blocks_invalid_order(): void
    {
        // Create order with zero amount (ToProcessingTransition requires amount > 0)
        $order = $this->createOrderWithInitialState([
            'order_number' => 'ORD-E2E-007',
            'customer_name' => 'Zero Amount Test',
            'total_amount' => 0,
        ]);

        // The ToProcessingTransition.canTransition() checks for total_amount > 0
        $this->expectException(TransitionNotAllowed::class);
        $order->state->transitionTo(ProcessingState::class, ['processing_notes' => 'Test']);
    }

    /**
     * Test workflow data integrity after multiple transitions
     */
    public function test_data_integrity_throughout_workflow(): void
    {
        $order = $this->createOrderWithInitialState([
            'order_number' => 'ORD-INTEGRITY',
            'customer_name' => 'Data Integrity Test',
            'customer_email' => 'integrity@test.com',
            'total_amount' => 1000.00,
        ]);

        // Verify initial data
        $originalNumber = $order->order_number;
        $originalName = $order->customer_name;
        $originalEmail = $order->customer_email;
        $originalAmount = $order->total_amount;

        // Execute full workflow
        $order->state->transitionTo(ProcessingState::class, [
            'processing_notes' => 'Processing note',
            'estimated_delivery' => '2026-02-01',
        ]);
        $order->refresh();

        $order->state->transitionTo(ShippedState::class, $this->getShippingData([
            'tracking_number' => 'TRACK-INTEGRITY',
            'carrier' => 'dhl',
            'shipping_notes' => 'Handle with care',
        ]));
        $order->refresh();

        $order->state->transitionTo(DeliveredState::class);
        $order->refresh();

        // Verify original data is intact
        $this->assertEquals($originalNumber, $order->order_number);
        $this->assertEquals($originalName, $order->customer_name);
        $this->assertEquals($originalEmail, $order->customer_email);
        $this->assertEquals($originalAmount, $order->total_amount);

        // Verify transition data was added
        $this->assertEquals('Processing note', $order->processing_notes);
        $this->assertEquals('TRACK-INTEGRITY', $order->tracking_number);
        $this->assertEquals('dhl', $order->carrier);
    }

    /**
     * Test state metadata is accessible at each step
     */
    public function test_state_metadata_accessible(): void
    {
        $order = $this->createOrderWithInitialState([
            'order_number' => 'ORD-META',
            'customer_name' => 'Metadata Test',
            'total_amount' => 125.00,
        ]);

        // Pending state metadata
        $this->assertEquals('Pending', $order->state->getLabel());
        $this->assertNotEmpty($order->state->getDescription());

        // Processing state metadata
        $order->state->transitionTo(ProcessingState::class, ['processing_notes' => 'Test']);
        $order->refresh();
        $this->assertEquals('Processing', $order->state->getLabel());

        // Shipped state metadata
        $order->state->transitionTo(ShippedState::class, $this->getShippingData([
            'tracking_number' => 'T999',
            'carrier' => 'ups',
        ]));
        $order->refresh();
        $this->assertEquals('Shipped', $order->state->getLabel());

        // Delivered state metadata
        $order->state->transitionTo(DeliveredState::class);
        $order->refresh();
        $this->assertEquals('Delivered', $order->state->getLabel());
    }

    /**
     * Test workflow with user assignment during shipping
     */
    public function test_workflow_assigns_user_on_shipping(): void
    {
        $order = $this->createOrderWithInitialState([
            'order_number' => 'ORD-ASSIGN',
            'customer_name' => 'Assignment Test',
            'total_amount' => 200.00,
        ]);

        // Transition to Processing
        $order->state->transitionTo(ProcessingState::class, ['processing_notes' => 'Test']);
        $order->refresh();

        // No assignments yet
        $this->assertEmpty($order->getAssignedUserIds());

        // Transition to Shipped with user assignment
        $order->state->transitionTo(ShippedState::class, $this->getShippingData());
        $order->refresh();

        // Verify user was assigned
        $assignedIds = $order->getAssignedUserIds();
        $this->assertContains($this->shippingUser->id, $assignedIds);
    }

    /**
     * Test shipping history contains tracking info
     * Note: Uses $order->transitionTo() (model method) to trigger logging
     *
     * @noinspection PhpUnhandledExceptionInspection
     */
    public function test_shipping_history_contains_tracking_info(): void
    {
        $order = $this->createOrderWithInitialState([
            'order_number' => 'ORD-TRACKING',
            'customer_name' => 'Tracking Test',
            'total_amount' => 300.00,
        ]);

        // Transition to Processing - use model's transitionTo to log history
        $order->transitionTo(ProcessingState::class, ['processing_notes' => 'Test']);
        $order->refresh();

        // Transition to Shipped - use model's transitionTo to log history
        $order->transitionTo(ShippedState::class, $this->getShippingData([
            'tracking_number' => 'TRACK-SPECIAL-123',
            'carrier' => 'fedex',
        ]));

        // Get shipping transition history
        $history = WorkflowStateTransition::where('transitionable_type', Order::class)
            ->where('transitionable_id', $order->id)
            ->where('to_state', ShippedState::class)
            ->first();

        $this->assertNotNull($history);
        $this->assertNotNull($history->notes);
        $this->assertStringContainsString('TRACK-SPECIAL-123', $history->notes);
        $this->assertStringContainsString('FEDEX', $history->notes);
    }
}
