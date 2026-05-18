<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\UI;

use ReflectionMethod;
use ReflectionProperty;
use RoBYCoNTe\FilamentFlow\Forms\Components\StateSelect;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * HasStateOptions Trait Tests
 *
 * Tests the HasStateOptions trait through StateSelect which uses it.
 */
class HasStateOptionsTest extends TestCase
{
    /**
     * Test that respectTransitions defaults to true.
     */
    public function test_respect_transitions_true_by_default(): void
    {
        $select = StateSelect::make('state');

        $property = new ReflectionProperty($select, 'respectTransitions');
        $property->setAccessible(true);

        $this->assertTrue($property->getValue($select));
    }

    /**
     * Test that respectTransitions can be explicitly set.
     */
    public function test_respect_transitions_can_be_set(): void
    {
        $select = StateSelect::make('state');

        $select->respectTransitions(false);

        $property = new ReflectionProperty($select, 'respectTransitions');
        $property->setAccessible(true);

        $this->assertFalse($property->getValue($select));

        $select->respectTransitions(true);

        $this->assertTrue($property->getValue($select));
    }

    /**
     * Test that ignoreTransitions sets respectTransitions to false.
     */
    public function test_ignore_transitions_sets_false(): void
    {
        $select = StateSelect::make('state');

        $select->ignoreTransitions();

        $property = new ReflectionProperty($select, 'respectTransitions');
        $property->setAccessible(true);

        $this->assertFalse($property->getValue($select));
    }

    /**
     * Test extractStateClass returns null for null input.
     */
    public function test_extract_state_class_null(): void
    {
        $select = StateSelect::make('state');

        $method = new ReflectionMethod($select, 'extractStateClass');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($select, null));
    }

    /**
     * Test extractStateClass returns the cast directly for regular casts.
     */
    public function test_extract_state_class_regular(): void
    {
        $select = StateSelect::make('state');

        $method = new ReflectionMethod($select, 'extractStateClass');
        $method->setAccessible(true);

        $result = $method->invoke($select, 'App\\States\\OrderState');

        $this->assertEquals('App\\States\\OrderState', $result);
    }

    /**
     * Test extractStateClass extracts the state class from FlexibleStateCast format.
     */
    public function test_extract_state_class_flexible_cast(): void
    {
        $select = StateSelect::make('state');

        $method = new ReflectionMethod($select, 'extractStateClass');
        $method->setAccessible(true);

        $result = $method->invoke(
            $select,
            'RoBYCoNTe\\FilamentFlow\\Casts\\FlexibleStateCast:App\\States\\Order\\OrderState'
        );

        $this->assertEquals('App\\States\\Order\\OrderState', $result);
    }
}
