<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\CodeFirst;

use RoBYCoNTe\FilamentFlow\Models\WorkflowStateTransition;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\DeliveredState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ShippedState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Tests for Transition History Notes Feature
 *
 * These tests verify that:
 * - Notes from getHistoryNotes() method are saved to history
 * - Notes include data from both record and form
 * - Config option can enable/disable notes logging
 * - Fallback to transition_notes field works
 */
class TransitionHistoryNotesTest extends TestCase
{
    // ==========================================
    // INTEGRATION TESTS - Notes from getHistoryNotes()
    // ==========================================

    /**
     * Test that notes from getHistoryNotes() are saved in transition history
     */
    public function test_history_notes_saved_from_transition_class(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-NOTES-001',
            'customer_name' => 'John Doe',
            'total_amount' => 100.00,
        ]);

        $order->state = new PendingState($order);
        $order->save();

        // Transition with form data
        $order->transitionTo(ProcessingState::class, [
            'processing_notes' => 'Rush order',
            'estimated_delivery' => '2026-02-15',
        ]);

        // Get the transition history
        $history = WorkflowStateTransition::where('transitionable_id', $order->id)
            ->where('transitionable_type', Order::class)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($history);
        $this->assertNotNull($history->notes);

        // Verify notes contain record data
        $this->assertStringContainsString('ORD-NOTES-001', $history->notes);
        $this->assertStringContainsString('John Doe', $history->notes);

        // Verify notes contain form data
        $this->assertStringContainsString('Rush order', $history->notes);
        $this->assertStringContainsString('2026-02-15', $history->notes);
    }

    /**
     * Test that notes include order amount and tracking info
     */
    public function test_shipped_transition_notes_include_tracking(): void
    {
        $user = $this->createTestUser(['name' => 'Shipper', 'email' => 'shipper-notes@test.com']);

        $order = Order::create([
            'order_number' => 'ORD-NOTES-002',
            'customer_name' => 'Jane Doe',
            'total_amount' => 250.50,
            'processed_at' => now(),
        ]);

        $order->state = new ProcessingState($order);
        $order->save();

        // Transition to shipped
        $order->transitionTo(ShippedState::class, [
            'assigned_users' => [$user->id],
            'tracking_number' => 'TRK123456',
            'carrier' => 'fedex',
            'shipping_notes' => 'Handle with care',
        ]);

        // Get the transition history
        $history = WorkflowStateTransition::where('transitionable_id', $order->id)
            ->where('transitionable_type', Order::class)
            ->where('to_state', ShippedState::class)
            ->first();

        $this->assertNotNull($history);
        $this->assertNotNull($history->notes);

        // Verify notes contain record data (order number and amount)
        $this->assertStringContainsString('ORD-NOTES-002', $history->notes);
        $this->assertStringContainsString('250.50', $history->notes);

        // Verify notes contain form data
        $this->assertStringContainsString('TRK123456', $history->notes);
        $this->assertStringContainsString('FEDEX', $history->notes);
        $this->assertStringContainsString('handler(s) assigned', $history->notes);
        $this->assertStringContainsString('Handle with care', $history->notes);
    }

    /**
     * Test delivered transition notes include signature status
     */
    public function test_delivered_transition_notes_include_signature(): void
    {
        $this->createTestUser(['name' => 'Deliverer', 'email' => 'deliverer-notes@test.com']);

        $order = Order::create([
            'order_number' => 'ORD-NOTES-003',
            'customer_name' => 'Bob Smith',
            'total_amount' => 500.00,
            'processed_at' => now(),
            'shipped_at' => now(),
            'tracking_number' => 'TRK-DELIVERED',
        ]);

        $order->state = new ShippedState($order);
        $order->save();

        // Transition to delivered with signature
        $order->transitionTo(DeliveredState::class, [
            'signature_received' => true,
            'delivery_notes' => 'Left at front door',
        ]);

        // Get the transition history
        $history = WorkflowStateTransition::where('transitionable_id', $order->id)
            ->where('transitionable_type', Order::class)
            ->where('to_state', DeliveredState::class)
            ->first();

        $this->assertNotNull($history);
        $this->assertNotNull($history->notes);

        // Verify notes contain record data
        $this->assertStringContainsString('ORD-NOTES-003', $history->notes);
        $this->assertStringContainsString('TRK-DELIVERED', $history->notes);

        // Verify notes contain form data
        $this->assertStringContainsString('Signature: YES', $history->notes);
        $this->assertStringContainsString('Left at front door', $history->notes);
    }

    /**
     * Test full workflow notes are recorded at each step
     */
    public function test_full_workflow_notes_recorded(): void
    {
        $user = $this->createTestUser(['name' => 'Handler', 'email' => 'handler-notes@test.com']);

        $order = Order::create([
            'order_number' => 'ORD-FULL-NOTES',
            'customer_name' => 'Full Test',
            'total_amount' => 999.99,
        ]);

        $order->state = new PendingState($order);
        $order->save();

        // Step 1: Pending -> Processing
        $order->transitionTo(ProcessingState::class, [
            'processing_notes' => 'Step 1 notes',
        ]);
        $order->refresh();

        // Step 2: Processing -> Shipped
        $order->transitionTo(ShippedState::class, [
            'assigned_users' => [$user->id],
            'tracking_number' => 'TRK-FULL',
            'carrier' => 'ups',
        ]);
        $order->refresh();

        // Step 3: Shipped -> Delivered
        $order->transitionTo(DeliveredState::class, [
            'signature_received' => true,
        ]);

        // Get all transitions for this order
        $histories = WorkflowStateTransition::where('transitionable_id', $order->id)
            ->where('transitionable_type', Order::class)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $histories);

        // Check each transition has notes
        foreach ($histories as $history) {
            $this->assertNotNull($history->notes, "Notes should be present for transition to $history->to_state");
            $this->assertStringContainsString('ORD-FULL-NOTES', $history->notes);
        }
    }

    // ==========================================
    // UNIT TESTS - Configuration and Fallback
    // ==========================================

    /**
     * Test that notes are null when config is disabled
     */
    public function test_notes_null_when_config_disabled(): void
    {
        // Disable notes logging
        config(['filament-flow.log_transition_notes' => false]);

        $order = Order::create([
            'order_number' => 'ORD-DISABLED',
            'customer_name' => 'Config Test',
            'total_amount' => 100.00,
        ]);

        $order->state = new PendingState($order);
        $order->save();

        $order->transitionTo(ProcessingState::class, [
            'processing_notes' => 'This should not be saved',
        ]);

        $history = WorkflowStateTransition::where('transitionable_id', $order->id)
            ->where('transitionable_type', Order::class)
            ->first();

        $this->assertNull($history->notes);

        // Re-enable for other tests
        config(['filament-flow.log_transition_notes' => true]);
    }

    /**
     * Test transition_notes field fallback
     */
    public function test_transition_notes_field_fallback(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FALLBACK',
            'customer_name' => 'Fallback Test',
            'total_amount' => 100.00,
        ]);

        $order->state = new PendingState($order);
        $order->save();

        // Use transition_notes field (fallback) along with other data
        // Note: ToProcessingTransition has getHistoryNotes, so it will be used instead
        // This test verifies the priority system works
        $order->transitionTo(ProcessingState::class, [
            'processing_notes' => 'Primary notes',
            'transition_notes' => 'Fallback notes - should not be used',
        ]);

        $history = WorkflowStateTransition::where('transitionable_id', $order->id)
            ->where('transitionable_type', Order::class)
            ->first();

        $this->assertNotNull($history->notes);
        // getHistoryNotes takes priority, so processing_notes should be in there
        $this->assertStringContainsString('Primary notes', $history->notes);
    }

    /**
     * Test notes with empty form data still includes record info
     */
    public function test_notes_with_empty_form_data(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-EMPTY-FORM',
            'customer_name' => 'Empty Form Test',
            'total_amount' => 150.00,
        ]);

        $order->state = new PendingState($order);
        $order->save();

        // Transition without any form data
        $order->transitionTo(ProcessingState::class);

        $history = WorkflowStateTransition::where('transitionable_id', $order->id)
            ->where('transitionable_type', Order::class)
            ->first();

        // Notes should still contain record info even without form data
        $this->assertNotNull($history->notes);
        $this->assertStringContainsString('ORD-EMPTY-FORM', $history->notes);
        $this->assertStringContainsString('Empty Form Test', $history->notes);
    }

    /**
     * Test that getHistoryNotes has access to both record and form data
     */
    public function test_get_history_notes_has_full_context(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-CONTEXT',
            'customer_name' => 'Context Test Customer',
            'customer_email' => 'context@test.com',
            'total_amount' => 1234.56,
        ]);

        $order->state = new PendingState($order);
        $order->save();

        $order->transitionTo(ProcessingState::class, [
            'processing_notes' => 'Custom processing note',
            'estimated_delivery' => '2026-03-01',
        ]);

        $history = WorkflowStateTransition::where('transitionable_id', $order->id)
            ->where('transitionable_type', Order::class)
            ->first();

        $this->assertNotNull($history->notes);

        // Record data
        $this->assertStringContainsString('ORD-CONTEXT', $history->notes);
        $this->assertStringContainsString('Context Test Customer', $history->notes);

        // Form data
        $this->assertStringContainsString('Custom processing note', $history->notes);
        $this->assertStringContainsString('2026-03-01', $history->notes);
    }
}
