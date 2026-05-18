<?php

/** @noinspection PhpParamsInspection */

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\CodeFirst;

use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\DeliveredState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\OrderState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\PendingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ProcessingState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\States\ShippedState;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;
use Spatie\ModelStates\State;

/**
 * Test state definition and metadata using Code-First approach (Spatie Model States)
 */
class StateDefinitionTest extends TestCase
{
    /**
     * Test that a state has a label
     */
    public function test_state_has_label(): void
    {
        $state = new PendingState(null);

        $this->assertNotEmpty($state->getLabel());
        $this->assertEquals('Pending', $state->getLabel());
    }

    /**
     * Test that different states have different labels
     */
    public function test_states_have_different_labels(): void
    {
        $pending = new PendingState(null);
        $processing = new ProcessingState(null);
        $shipped = new ShippedState(null);
        $delivered = new DeliveredState(null);

        $this->assertEquals('Pending', $pending->getLabel());
        $this->assertEquals('Processing', $processing->getLabel());
        $this->assertEquals('Shipped', $shipped->getLabel());
        $this->assertEquals('Delivered', $delivered->getLabel());
    }

    /**
     * Test that a state has a description
     */
    public function test_state_has_description(): void
    {
        $state = new PendingState(null);

        $this->assertNotEmpty($state->getDescription());
        $this->assertEquals('Order is pending processing', $state->getDescription());
    }

    /**
     * Test that different states have different descriptions
     */
    public function test_states_have_different_descriptions(): void
    {
        $pending = new PendingState(null);
        $processing = new ProcessingState(null);
        $shipped = new ShippedState(null);
        $delivered = new DeliveredState(null);

        $this->assertNotEmpty($pending->getDescription());
        $this->assertNotEmpty($processing->getDescription());
        $this->assertNotEmpty($shipped->getDescription());
        $this->assertNotEmpty($delivered->getDescription());

        $this->assertNotEquals($pending->getDescription(), $processing->getDescription());
    }

    /**
     * Test that states have sort order
     */
    public function test_state_has_sort_order(): void
    {
        $this->assertEquals(10, PendingState::getSortOrder());
        $this->assertEquals(20, ProcessingState::getSortOrder());
        $this->assertEquals(30, ShippedState::getSortOrder());
        $this->assertEquals(40, DeliveredState::getSortOrder());
    }

    /**
     * Test that states are properly sorted by sort order
     */
    public function test_states_are_sorted_by_order(): void
    {
        $pending = PendingState::getSortOrder();
        $processing = ProcessingState::getSortOrder();
        $shipped = ShippedState::getSortOrder();
        $delivered = DeliveredState::getSortOrder();

        $this->assertLessThan($processing, $pending);
        $this->assertLessThan($shipped, $processing);
        $this->assertLessThan($delivered, $shipped);
    }

    /**
     * Test that state class inheritance is correct
     *
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    public function test_state_class_inheritance(): void
    {
        $pending = new PendingState(null);
        $processing = new ProcessingState(null);

        $this->assertInstanceOf(State::class, $pending);
        $this->assertInstanceOf(State::class, $processing);
        $this->assertInstanceOf(OrderState::class, $pending);
        $this->assertInstanceOf(OrderState::class, $processing);
    }
}
