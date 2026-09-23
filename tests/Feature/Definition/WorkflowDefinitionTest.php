<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Definition;

use RoBYCoNTe\FilamentFlow\Definition\State;
use RoBYCoNTe\FilamentFlow\Definition\Transition;
use RoBYCoNTe\FilamentFlow\Definition\WorkflowDefinition;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * The definition of a workflow: it builds its states and its transitions, and survives a round
 * trip through an array.
 */
class WorkflowDefinitionTest extends TestCase
{
    public function test_builds_states_and_transitions(): void
    {
        $definition = WorkflowDefinition::make('order', Order::class)
            ->state(State::make('draft', 'Draft')->initial())
            ->state(State::make('paid', 'Paid')->final())
            ->transition(Transition::make('pay', 'draft', 'paid')->label('Pay'));

        $this->assertSame('order', $definition->getName());
        $this->assertSame(Order::class, $definition->getModelType());
        $this->assertCount(2, $definition->getStates());
        $this->assertCount(1, $definition->getTransitions());
        $this->assertSame('Draft', $definition->stateByName('draft')?->toArray()['label']);
        $this->assertNull($definition->stateByName('missing'));
    }

    public function test_round_trips_through_array(): void
    {
        $definition = WorkflowDefinition::make('order', Order::class)
            ->stateColumn('status')
            ->active(false)
            ->autoAssignCreator(true, 'primary')
            ->metadata(['team' => 'ops'])
            ->state(State::make('draft', 'Draft')->initial()->color('gray'))
            ->state(State::make('paid', 'Paid')->final()->color('success'))
            ->transition(
                Transition::make('pay', 'draft', 'paid')
                    ->label('Pay')
                    ->confirm()
                    ->formulaCondition('total > 0', 'Total must be positive.')
            );

        $restored = WorkflowDefinition::fromArray($definition->toArray());

        $this->assertSame($definition->toArray(), $restored->toArray());
    }
}
