<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Infolists;

use RoBYCoNTe\FilamentFlow\Infolists\Components\OwnershipHistoryEntry;
use RoBYCoNTe\FilamentFlow\Models\WorkflowOwnerChange;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * The history of the handovers of a record, as the entry tells it: who held it before, who
 * holds it now, what the previous holder kept and who made the change.
 */
class OwnershipHistoryEntryTest extends TestCase
{
    protected Order $order;

    protected User $first;

    protected User $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->first = $this->createTestUser(['name' => 'Mario Rossi', 'email' => 'mario@test.com']);
        $this->second = $this->createTestUser(['name' => 'Luisa Bianchi', 'email' => 'luisa@test.com']);

        $this->order = Order::create([
            'order_number' => 'ORD-OHE-001',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'user_id' => $this->second->id,
        ]);

        WorkflowOwnerChange::create([
            'changeable_type' => $this->order->getMorphClass(),
            'changeable_id' => $this->order->getKey(),
            'from_user_id' => $this->first->id,
            'to_user_id' => $this->second->id,
            'changed_by' => $this->first->id,
            'retention' => 'viewer',
            'note' => 'Cambio di referente',
            'changed_at' => now(),
        ]);
    }

    public function test_the_entry_carries_a_translated_label(): void
    {
        $this->assertSame(
            __('filament-flow::messages.ownership_history_label'),
            OwnershipHistoryEntry::make()->getLabel(),
        );
    }

    public function test_it_reads_the_handovers_of_the_record(): void
    {
        $history = OwnershipHistoryEntry::make('history')->getHistory($this->order);

        $this->assertCount(1, $history);
        $this->assertSame('Mario Rossi', $history[0]['from']);
        $this->assertSame('MR', $history[0]['from_initials']);
        $this->assertSame('Luisa Bianchi', $history[0]['to']);
        $this->assertSame('LB', $history[0]['to_initials']);
        $this->assertSame('Mario Rossi', $history[0]['by']);
        $this->assertSame('viewer', $history[0]['retention']);
        $this->assertSame(__('filament-flow::messages.retention_viewer'), $history[0]['retention_label']);
        $this->assertTrue($history[0]['retained']);
        $this->assertSame('Cambio di referente', $history[0]['note']);
    }

    public function test_the_limit_cuts_the_history(): void
    {
        WorkflowOwnerChange::create([
            'changeable_type' => $this->order->getMorphClass(),
            'changeable_id' => $this->order->getKey(),
            'from_user_id' => $this->second->id,
            'to_user_id' => $this->first->id,
            'retention' => 'none',
            'changed_at' => now()->addMinute(),
        ]);

        $entry = OwnershipHistoryEntry::make('history')->limit(1);

        $this->assertSame(1, $entry->getLimit());
        $this->assertCount(1, $entry->getHistory($this->order));
        $this->assertSame('Mario Rossi', $entry->getHistory($this->order)[0]['to']);
    }

    public function test_a_record_that_never_changed_hands_has_no_history(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-OHE-002',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'user_id' => $this->first->id,
        ]);

        $this->assertSame([], OwnershipHistoryEntry::make('history')->getHistory($order));
    }
}
