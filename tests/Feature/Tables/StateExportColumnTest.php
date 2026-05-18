<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Tables;

use RoBYCoNTe\FilamentFlow\Tables\Columns\StateExportColumn;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class StateExportColumnTest extends TestCase
{
    public function test_make_creates_instance(): void
    {
        $column = StateExportColumn::make('state');

        $this->assertInstanceOf(StateExportColumn::class, $column);
    }

    public function test_default_name_is_state(): void
    {
        $defaultName = StateExportColumn::getDefaultName();

        $this->assertEquals('state', $defaultName);
    }

    public function test_state_attribute_setter(): void
    {
        $column = StateExportColumn::make('state')
            ->stateAttribute('custom_state');

        $this->assertEquals('custom_state', $column->getStateAttribute());
    }

    public function test_state_attribute_default(): void
    {
        $column = StateExportColumn::make('state');

        // Without a record and without explicit stateAttribute, falls back to 'state'
        $this->assertEquals('state', $column->getStateAttribute());
    }

    public function test_get_state_label_from_php_state(): void
    {
        // Create an order and explicitly set the Spatie state
        $order = Order::create([
            'order_number' => 'ORD-EXPORT-001',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ]);
        $order->state = new PendingState($order);
        $order->save();
        $order->refresh();

        // The state should be PendingState, which has getLabel() = 'Pending'
        $state = $order->state;
        $this->assertInstanceOf(PendingState::class, $state);

        // PendingState implements getLabel() returning 'Pending'
        $this->assertEquals('Pending', $state->getLabel());
    }
}
