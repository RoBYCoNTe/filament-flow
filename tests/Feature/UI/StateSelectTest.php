<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\UI;

use Filament\Forms\Components\Select;
use ReflectionProperty;
use RoBYCoNTe\FilamentFlow\Contracts\HasStateAttributes as HasStateAttributesContract;
use RoBYCoNTe\FilamentFlow\Forms\Components\StateSelect;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * StateSelect UI Component Tests
 *
 * Tests instantiation and fluent API of the StateSelect form component.
 */
class StateSelectTest extends TestCase
{
    /**
     * Test StateSelect can be instantiated via static make().
     */
    public function test_can_be_created(): void
    {
        $select = StateSelect::make('state');

        $this->assertInstanceOf(StateSelect::class, $select);
    }

    /**
     * Test StateSelect extends Filament's Select component.
     */
    public function test_is_select_component(): void
    {
        $select = StateSelect::make('state');

        $this->assertInstanceOf(Select::class, $select);
    }

    /**
     * Test StateSelect implements HasStateAttributes contract.
     */
    public function test_has_state_attributes_trait(): void
    {
        $select = StateSelect::make('state');

        $this->assertInstanceOf(HasStateAttributesContract::class, $select);
        $this->assertTrue(method_exists($select, 'getAttribute'));
        $this->assertTrue(method_exists($select, 'attribute'));
    }

    /**
     * Test that respectTransitions defaults to true.
     */
    public function test_respect_transitions_default_true(): void
    {
        $select = StateSelect::make('state');

        $property = new ReflectionProperty($select, 'respectTransitions');
        $property->setAccessible(true);

        $this->assertTrue($property->getValue($select));
    }

    /**
     * Test ignoreTransitions() returns $this for fluent chaining and changes behavior.
     */
    public function test_ignore_transitions_fluent(): void
    {
        $select = StateSelect::make('state');

        $result = $select->ignoreTransitions();

        $this->assertSame($select, $result);

        $property = new ReflectionProperty($select, 'respectTransitions');
        $property->setAccessible(true);

        $this->assertFalse($property->getValue($select));
    }
}
