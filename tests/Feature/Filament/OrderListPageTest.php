<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Filament;

use Livewire\Livewire;
use RoBYCoNTe\FilamentFlow\Tests\FilamentTestCase;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Resources\OrderResource\Pages\ListOrders;

class OrderListPageTest extends FilamentTestCase
{
    private array $workflowData;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflowData = $this->createFullWorkflow();
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'list-test@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($this->user);
    }

    public function test_list_page_renders(): void
    {
        Livewire::test(ListOrders::class)
            ->assertSuccessful();
    }

    public function test_table_shows_records(): void
    {
        $order1 = $this->createOrderInState('pending');
        $order2 = $this->createOrderInState('processing');

        Livewire::test(ListOrders::class)
            ->assertCanSeeTableRecords([$order1, $order2]);
    }

    public function test_table_has_columns(): void
    {
        Livewire::test(ListOrders::class)
            ->assertTableColumnExists('order_number')
            ->assertTableColumnExists('customer_name')
            ->assertTableColumnExists('total_amount')
            ->assertTableColumnExists('state');
    }

    public function test_column_visible_when_at_least_one_state_shows_it(): void
    {
        // carrier is: hidden in pending, not defined in processing, visible in shipped
        // So it IS visible in at least one state -> should render
        $this->createOrderInState('pending');

        Livewire::test(ListOrders::class)
            ->assertTableColumnExists('carrier');
    }

    public function test_table_without_workflow_shows_all(): void
    {
        config()->set('filament-flow.enabled', false);

        $this->createOrderInState('pending');

        Livewire::test(ListOrders::class)
            ->assertTableColumnExists('order_number')
            ->assertTableColumnExists('tracking_number')
            ->assertTableColumnExists('carrier');
    }

    public function test_table_count_records(): void
    {
        $this->createOrderInState('pending');
        $this->createOrderInState('processing');
        $this->createOrderInState('shipped');

        Livewire::test(ListOrders::class)
            ->assertCountTableRecords(3);
    }

    public function test_table_search_works(): void
    {
        $order = $this->createOrderInState('pending', [
            'order_number' => 'UNIQUE-SEARCH-123',
        ]);

        $this->createOrderInState('processing', [
            'order_number' => 'OTHER-ORDER-456',
        ]);

        Livewire::test(ListOrders::class)
            ->searchTable('UNIQUE-SEARCH')
            ->assertCanSeeTableRecords([$order])
            ->assertCountTableRecords(1);
    }
}
