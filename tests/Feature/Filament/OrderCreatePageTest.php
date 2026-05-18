<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Filament;

use Livewire\Livewire;
use RoBYCoNTe\FilamentFlow\Tests\FilamentTestCase;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Resources\OrderResource\Pages\CreateOrder;

class OrderCreatePageTest extends FilamentTestCase
{
    private array $workflowData;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflowData = $this->createFullWorkflow();
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'create-test@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($this->user);
    }

    public function test_create_page_renders(): void
    {
        Livewire::test(CreateOrder::class)
            ->assertSuccessful();
    }

    public function test_form_has_all_fields(): void
    {
        Livewire::test(CreateOrder::class)
            ->assertFormFieldExists('order_number')
            ->assertFormFieldExists('customer_name')
            ->assertFormFieldExists('customer_email')
            ->assertFormFieldExists('total_amount')
            ->assertFormFieldExists('notes');
    }

    public function test_field_hidden_when_locked(): void
    {
        // tracking_number is LOCKED in pending (initial) state
        Livewire::test(CreateOrder::class)
            ->assertFormFieldHidden('tracking_number');
    }

    public function test_field_disabled_when_readonly(): void
    {
        // processing_notes is readonly in pending state
        Livewire::test(CreateOrder::class)
            ->assertFormFieldDisabled('processing_notes');
    }

    public function test_field_visible_by_default(): void
    {
        // Fields without special permissions are visible
        Livewire::test(CreateOrder::class)
            ->assertFormFieldExists('order_number');
    }

    public function test_create_record_sets_initial_state(): void
    {
        Livewire::test(CreateOrder::class)
            ->fillForm([
                'order_number' => 'ORD-CREATE-001',
                'customer_name' => 'Test Customer',
                'customer_email' => 'test@example.com',
                'total_amount' => 150.00,
            ])
            ->call('create');

        $order = Order::where('order_number', 'ORD-CREATE-001')->first();
        $this->assertNotNull($order);
        $this->assertEquals('pending', $order->state);
    }

    public function test_create_allowed_for_authenticated(): void
    {
        // @authenticated access rule is set on pending state for create
        // Just verify the page renders (which means canCreate returned true)
        Livewire::test(CreateOrder::class)
            ->assertSuccessful();
    }

    public function test_carrier_hidden_in_initial_state(): void
    {
        // carrier is hidden in pending state
        Livewire::test(CreateOrder::class)
            ->assertFormFieldHidden('carrier');
    }

    public function test_creation_sets_initial_state_from_workflow(): void
    {
        // Verify that the WorkflowCreationService sets the state from the initial state
        Livewire::test(CreateOrder::class)
            ->fillForm([
                'order_number' => 'ORD-STATE-001',
                'customer_name' => 'State Test',
                'customer_email' => 'state@test.com',
                'total_amount' => 200.00,
            ])
            ->call('create');

        $order = Order::where('order_number', 'ORD-STATE-001')->first();
        $this->assertNotNull($order);
        // The initial state name is 'pending' as configured in createFullWorkflow()
        $this->assertEquals('pending', $order->state);
    }
}
