<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Services;

use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use RoBYCoNTe\FilamentFlow\Events\WorkflowOwnerChanged;
use RoBYCoNTe\FilamentFlow\Models\WorkflowOwnerChange;
use RoBYCoNTe\FilamentFlow\Services\OwnershipTransfer;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Handing a record over, as the engine does it: the new owner, what the previous one keeps, the
 * handover written down, and the event raised — none of it needing the panel.
 */
class OwnershipTransferTest extends TestCase
{
    protected OwnershipTransfer $transfers;

    protected Order $order;

    protected User $first;

    protected User $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transfers = app(OwnershipTransfer::class);

        $this->first = $this->createTestUser(['name' => 'Mario Rossi', 'email' => 'mario@test.com']);
        $this->second = $this->createTestUser(['name' => 'Luisa Bianchi', 'email' => 'luisa@test.com']);

        $this->order = Order::create([
            'order_number' => 'ORD-OWN-001',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'user_id' => $this->first->id,
        ]);
    }

    public function test_it_gives_the_record_to_another_person(): void
    {
        $this->transfers->transfer($this->order, $this->second->id, actor: $this->first);

        $this->assertSame($this->second->id, $this->order->fresh()->user_id);
    }

    public function test_nothing_is_kept_by_default(): void
    {
        $this->transfers->transfer($this->order, $this->second->id, actor: $this->first);

        $this->assertSame(0, $this->order->assignments()->count());
    }

    public function test_the_previous_holder_keeps_seeing_the_record(): void
    {
        $this->transfers->transfer(
            record: $this->order,
            toUserId: $this->second->id,
            retention: OwnershipTransfer::RETENTION_VIEWER,
            note: 'In ferie',
            actor: $this->first,
        );

        $assignment = $this->order->assignments()->where('user_id', $this->first->id)->first();

        $this->assertNotNull($assignment);
        $this->assertSame('viewer', $assignment->assignment_type);
        $this->assertTrue($assignment->override_view);
        $this->assertTrue((bool) $assignment->getMetadata('owner_transfer'));
        $this->assertSame('In ferie', $assignment->getMetadata('transfer_note'));
    }

    public function test_the_previous_holder_stays_on_the_work(): void
    {
        $this->transfers->transfer(
            record: $this->order,
            toUserId: $this->second->id,
            retention: OwnershipTransfer::RETENTION_SECONDARY,
            actor: $this->first,
        );

        $assignment = $this->order->assignments()->where('user_id', $this->first->id)->first();

        $this->assertNotNull($assignment);
        $this->assertSame('secondary', $assignment->assignment_type);
        $this->assertNull($assignment->override_view);
    }

    public function test_the_handover_is_written_down_and_announced(): void
    {
        Event::fake([WorkflowOwnerChanged::class]);

        $change = $this->transfers->transfer(
            record: $this->order,
            toUserId: $this->second->id,
            retention: OwnershipTransfer::RETENTION_VIEWER,
            note: 'Cambio di referente',
            actor: $this->first,
        );

        $this->assertSame($this->first->id, $change->from_user_id);
        $this->assertSame($this->second->id, $change->to_user_id);
        $this->assertSame($this->first->id, $change->changed_by);
        $this->assertSame('viewer', $change->retention);
        $this->assertSame('user_id', $change->owner_field);
        $this->assertSame('Cambio di referente', $change->note);

        $this->assertDatabaseCount('workflow_owner_changes', 1);

        Event::assertDispatched(WorkflowOwnerChanged::class, fn (WorkflowOwnerChanged $event): bool => $event->fromUserId === $this->first->id
            && $event->toUserId === $this->second->id
            && $event->retention === 'viewer'
            && $event->note === 'Cambio di referente');
    }

    public function test_a_record_that_changes_hands_twice_keeps_both(): void
    {
        $this->transfers->transfer($this->order, $this->second->id, actor: $this->first);
        $this->transfers->transfer($this->order, $this->first->id, actor: $this->second);

        $this->assertSame(2, WorkflowOwnerChange::query()->forRecord($this->order)->count());
        $this->assertSame($this->first->id, $this->order->fresh()->user_id);
    }

    public function test_the_owner_it_already_has_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->transfers->transfer($this->order, $this->first->id, actor: $this->first);
    }

    public function test_a_retention_nobody_knows_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->transfers->transfer($this->order, $this->second->id, retention: 'whatever', actor: $this->first);
    }
}
