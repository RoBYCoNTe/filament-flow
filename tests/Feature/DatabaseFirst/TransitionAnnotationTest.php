<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\DatabaseFirst;

use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * What a transition can leave on its row of history after it ran — a labelled value, such as a
 * protocol number, that the reader finds beside who did it and when.
 */
class TransitionAnnotationTest extends TestCase
{
    public function test_a_value_is_left_on_the_row_of_the_last_transition(): void
    {
        $order = $this->orderMovedToDone();

        $order->annotateTransition('protocol', 'Protocol', 'PROT-1', now());

        $history = $this->getLastTransition($order)->fresh();

        $this->assertTrue($history->has_metadata);
        $this->assertSame('Protocol', $history->annotations()['protocol']['label']);
        $this->assertSame('PROT-1', $history->annotations()['protocol']['value']);
        $this->assertNotEmpty($history->annotations()['protocol']['at']);
    }

    public function test_annotating_the_same_key_again_replaces_the_value(): void
    {
        $order = $this->orderMovedToDone();

        $order->annotateTransition('protocol', 'Protocol', 'PROT-1');
        $order->annotateTransition('protocol', 'Protocol', 'PROT-2');
        $order->annotateTransition('reference', 'Reference', 'REF-9');

        $annotations = $this->getLastTransition($order)->fresh()->annotations();

        $this->assertSame('PROT-2', $annotations['protocol']['value']);
        $this->assertSame('REF-9', $annotations['reference']['value']);
        $this->assertArrayNotHasKey('at', $annotations['reference'], 'A value without a date carries none.');
    }

    public function test_an_empty_value_leaves_nothing(): void
    {
        $order = $this->orderMovedToDone();

        $order->annotateTransition('protocol', 'Protocol', '  ');

        $this->assertSame([], $this->getLastTransition($order)->fresh()->annotations());
    }

    public function test_annotating_without_a_transition_does_nothing(): void
    {
        $order = $this->createOrder(['state' => 'pending']);

        $order->annotateTransition('protocol', 'Protocol', 'PROT-1');

        $this->assertNull($this->getLastTransition($order));
    }

    private function orderMovedToDone(): Order
    {
        $workflow = $this->createTestWorkflow();
        $pending = $this->createWorkflowState($workflow, ['name' => 'pending']);
        $done = $this->createWorkflowState($workflow, ['name' => 'done']);
        $this->createWorkflowTransition($workflow, $pending, $done);

        $order = $this->createOrder(['state' => 'pending']);
        $order->transitionTo('done');

        return $order;
    }

    private function createOrder(array $data = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-NOTE-'.uniqid(),
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ], $data));
    }
}
