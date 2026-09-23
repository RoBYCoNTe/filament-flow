<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Definition;

use RoBYCoNTe\FilamentFlow\Definition\Enums\Mutability;
use RoBYCoNTe\FilamentFlow\Definition\Enums\Visibility;
use RoBYCoNTe\FilamentFlow\Definition\State;
use RoBYCoNTe\FilamentFlow\Definition\StateField;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * A state: the flags that mark it as the first or a final one, and the field rules it carries
 * with their visibility and their mutability.
 */
class StateTest extends TestCase
{
    public function test_initial_and_final_flags(): void
    {
        $initial = State::make('draft', 'Draft')->initial();
        $final = State::make('done', 'Done')->final();

        $this->assertTrue($initial->isInitial());
        $this->assertFalse($final->isInitial());
        $this->assertSame(true, $final->toArray()['is_final']);
    }

    public function test_state_fields_carry_visibility_and_mutability(): void
    {
        $state = State::make('review', 'Review')->fields([
            StateField::make('costs')->locked()->required(),
            StateField::make('secret')->hidden(),
        ]);

        $fields = $state->stateFields();

        $this->assertCount(2, $fields);
        $this->assertSame(Mutability::Locked->value, $fields[0]->toArray()['mutability']);
        $this->assertTrue($fields[0]->toArray()['is_required']);
        $this->assertSame(Visibility::Hidden->value, $fields[1]->toArray()['visibility']);
    }
}
