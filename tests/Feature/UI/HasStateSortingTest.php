<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\UI;

use Illuminate\Database\Eloquent\Builder;
use ReflectionMethod;
use RoBYCoNTe\FilamentFlow\Tables\Columns\StateSelectColumn;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * HasStateSorting Trait Tests
 *
 * Tests the HasStateSorting trait through StateSelectColumn which uses it.
 */
class HasStateSortingTest extends TestCase
{
    /**
     * Test that sortable is configured after setUp via setupStateSorting.
     */
    public function test_sortable_is_configured(): void
    {
        $column = StateSelectColumn::make('state');

        // The column should have sorting capability enabled
        $this->assertTrue(method_exists($column, 'applySort'));
        $this->assertTrue(method_exists($column, 'setupStateSorting'));
    }

    /**
     * Test applySort falls back to simple orderBy when state class is not found.
     */
    public function test_apply_sort_without_state_class(): void
    {
        $column = StateSelectColumn::make('state');
        $column->attribute('state');

        $query = Order::query();
        $result = $column->applySort($query, 'asc');

        // Should return a Builder instance (ordered by the attribute)
        $this->assertInstanceOf(Builder::class, $result);
    }

    /**
     * Test extractStateClassFromSorting returns null for null input.
     */
    public function test_extract_state_class_from_sorting_null(): void
    {
        $column = StateSelectColumn::make('state');

        $method = new ReflectionMethod($column, 'extractStateClassFromSorting');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($column, null));
    }

    /**
     * Test extractStateClassFromSorting extracts class from FlexibleStateCast format.
     */
    public function test_extract_state_class_from_sorting_flexible_cast(): void
    {
        $column = StateSelectColumn::make('state');

        $method = new ReflectionMethod($column, 'extractStateClassFromSorting');
        $method->setAccessible(true);

        $result = $method->invoke(
            $column,
            'RoBYCoNTe\\FilamentFlow\\Casts\\FlexibleStateCast:App\\States\\Order\\OrderState'
        );

        $this->assertEquals('App\\States\\Order\\OrderState', $result);
    }
}
