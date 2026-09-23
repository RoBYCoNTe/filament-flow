<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Livewire;

use Livewire\Livewire;
use RoBYCoNTe\FilamentFlow\Livewire\AssignmentManager;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * The panel of the assignments has to be mountable wherever a host embeds it: a Filament
 * schema hands the record over in one shape, a Blade mount in another, and a host may only
 * know the id. What cannot be understood is ignored instead of breaking the page.
 */
class AssignmentManagerMountTest extends TestCase
{
    /** A model, as a Blade mount delivers it. */
    public function test_it_mounts_with_a_model(): void
    {
        $order = $this->order();

        Livewire::test(AssignmentManager::class, ['record' => $order])
            ->assertSuccessful()
            ->assertSet('recordId', $order->getKey())
            ->assertSet('recordType', $order::class);
    }

    /** An array, which is what a Filament schema delivers after the round trip on the wire. */
    public function test_it_mounts_with_an_array(): void
    {
        $order = $this->order();

        Livewire::test(AssignmentManager::class, [
            'record' => ['id' => $order->getKey()],
            'recordType' => $order::class,
        ])->assertSuccessful()->assertSet('recordId', $order->getKey());
    }

    /** An id and its class, for a host that knows nothing else. */
    public function test_it_mounts_with_an_identifier(): void
    {
        $order = $this->order();

        Livewire::test(AssignmentManager::class, [
            'record' => $order->getKey(),
            'recordType' => $order::class,
        ])->assertSuccessful()->assertSet('recordId', $order->getKey());
    }

    /**
     * A key that is not a number — a ULID, a UUID — must survive: casting it to int points
     * at a record that does not exist, and the panel comes out empty.
     */
    public function test_it_keeps_a_string_key(): void
    {
        Livewire::test(AssignmentManager::class, [
            'record' => '01m32b2aq4bhrj144dgqdgjr8v',
            'recordType' => Order::class,
        ])->assertSuccessful()
            ->assertSet('recordId', '01m32b2aq4bhrj144dgqdgjr8v');
    }

    /** And with no record at all, without exploding. */
    public function test_it_mounts_without_a_record(): void
    {
        Livewire::test(AssignmentManager::class)
            ->assertSuccessful()
            ->assertSet('recordId', null);
    }

    private function order(): Order
    {
        return Order::create([
            'order_number' => 'ORD-MOUNT-001',
            'customer_name' => 'John Doe',
            'total_amount' => 100.00,
        ]);
    }
}
