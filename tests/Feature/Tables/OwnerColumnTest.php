<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Tables;

use Illuminate\Support\Facades\DB;
use RoBYCoNTe\FilamentFlow\Models\WorkflowOwnerChange;
use RoBYCoNTe\FilamentFlow\Tables\Columns\OwnerColumn;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * The column that shows who holds a record — and that the record changed hands: from whom,
 * when, and how many times.
 */
class OwnerColumnTest extends TestCase
{
    protected Order $order;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->createTestUser([
            'name' => 'Mario Rossi',
            'email' => 'mario@test.com',
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-OWN-001',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'user_id' => $this->owner->id,
        ]);
    }

    public function test_it_reads_the_owner_from_the_configured_column(): void
    {
        $column = OwnerColumn::make('owner');

        $this->assertSame('user_id', $column->ownerField());

        $owner = $column->getOwner($this->order);

        $this->assertNotNull($owner);
        $this->assertEquals($this->owner->id, $owner['id']);
        $this->assertSame('Mario Rossi', $owner['name']);
        $this->assertSame('MR', $owner['initials']);
    }

    public function test_a_record_without_an_owner_has_none(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-OWN-002',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'user_id' => null,
        ]);

        $this->assertNull(OwnerColumn::make('owner')->getOwner($order));
        $this->assertSame([], OwnerColumn::make('owner')->getOwnerChanges($order));
    }

    public function test_it_reads_the_handovers_most_recent_first(): void
    {
        $second = $this->createTestUser(['name' => 'Luisa Bianchi', 'email' => 'luisa@test.com']);

        WorkflowOwnerChange::create([
            'changeable_type' => $this->order->getMorphClass(),
            'changeable_id' => $this->order->getKey(),
            'from_user_id' => $this->owner->id,
            'to_user_id' => $second->id,
            'retention' => 'viewer',
            'note' => 'In ferie',
            'changed_at' => now()->subDay(),
        ]);

        WorkflowOwnerChange::create([
            'changeable_type' => $this->order->getMorphClass(),
            'changeable_id' => $this->order->getKey(),
            'from_user_id' => $second->id,
            'to_user_id' => $this->owner->id,
            'retention' => 'none',
            'changed_at' => now(),
        ]);

        $changes = OwnerColumn::make('owner')->getOwnerChanges($this->order);

        $this->assertCount(2, $changes);
        $this->assertSame('Luisa Bianchi', $changes[0]['from']);
        $this->assertSame('Mario Rossi', $changes[0]['to']);
        $this->assertSame('none', $changes[0]['retention']);
        $this->assertNull($changes[0]['note']);
        $this->assertSame('Mario Rossi', $changes[1]['from']);
        $this->assertSame('viewer', $changes[1]['retention']);
        $this->assertSame('In ferie', $changes[1]['note']);
    }

    public function test_the_history_can_be_turned_off(): void
    {
        WorkflowOwnerChange::create([
            'changeable_type' => $this->order->getMorphClass(),
            'changeable_id' => $this->order->getKey(),
            'from_user_id' => $this->owner->id,
            'to_user_id' => $this->owner->id,
            'changed_at' => now(),
        ]);

        $column = OwnerColumn::make('owner')->withHistory(false);

        $this->assertFalse($column->getWithHistory());
        // The owner is still read: only the handovers are left out.
        $this->assertNotNull($column->getOwner($this->order));
    }

    /** The cell is part of the row: clicking it follows the record link, like any other. */
    public function test_the_cell_follows_the_row_link(): void
    {
        $this->assertFalse(OwnerColumn::make('owner')->isClickDisabled());

        // A host that wants it inert still can.
        $this->assertTrue(OwnerColumn::make('owner')->disabledClick()->isClickDisabled());
    }

    /** An owner the host already loaded is read from the record, not asked again to the database. */
    public function test_a_loaded_owner_is_not_queried_again(): void
    {
        $order = $this->order->fresh()->load('user');

        DB::enableQueryLog();
        $owner = OwnerColumn::make('owner')->getOwner($order);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertNotNull($owner);
        $this->assertSame('Mario Rossi', $owner['name']);
        $this->assertSame([], $queries, 'An owner already loaded costs no query.');
    }

    public function test_the_history_limit_is_configurable(): void
    {
        $column = OwnerColumn::make('owner')->historyLimit(2);

        $this->assertSame(2, $column->getHistoryLimit());
        $this->assertSame(5, OwnerColumn::make('owner')->getHistoryLimit());
    }
}
