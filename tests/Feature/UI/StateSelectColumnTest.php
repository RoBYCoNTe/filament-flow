<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\UI;

use Filament\Tables\Columns\SelectColumn;
use ReflectionProperty;
use RoBYCoNTe\FilamentFlow\Tables\Columns\StateSelectColumn;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * StateSelectColumn UI Component Tests
 *
 * Tests instantiation and fluent API of the StateSelectColumn table column.
 */
class StateSelectColumnTest extends TestCase
{
    /**
     * Test StateSelectColumn can be instantiated via static make().
     */
    public function test_can_be_created(): void
    {
        $column = StateSelectColumn::make('state');

        $this->assertInstanceOf(StateSelectColumn::class, $column);
    }

    /**
     * Test StateSelectColumn extends Filament's SelectColumn.
     */
    public function test_is_select_column_component(): void
    {
        $column = StateSelectColumn::make('state');

        $this->assertInstanceOf(SelectColumn::class, $column);
    }

    /**
     * Test the attribute setter returns $this and stores the value.
     */
    public function test_attribute_setter_and_getter(): void
    {
        $column = StateSelectColumn::make('state');

        $result = $column->attribute('custom_attribute');

        $this->assertSame($column, $result);

        $property = new ReflectionProperty($column, 'attribute');
        $property->setAccessible(true);

        $this->assertEquals('custom_attribute', $property->getValue($column));
    }

    /**
     * Test that attribute defaults to null when not explicitly set.
     */
    public function test_default_attribute_is_null(): void
    {
        $column = StateSelectColumn::make('state');

        $property = new ReflectionProperty($column, 'attribute');
        $property->setAccessible(true);

        $this->assertNull($property->getValue($column));
    }

    /**
     * Test that StateSelectColumn has sorting capabilities via HasStateSorting trait.
     */
    public function test_has_state_sorting(): void
    {
        $column = StateSelectColumn::make('state');

        $this->assertTrue(method_exists($column, 'setupStateSorting'));
        $this->assertTrue(method_exists($column, 'applySort'));
    }
}
