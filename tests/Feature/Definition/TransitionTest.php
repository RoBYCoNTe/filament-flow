<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Definition;

use RoBYCoNTe\FilamentFlow\Definition\SideEffect;
use RoBYCoNTe\FilamentFlow\Definition\Transition;
use RoBYCoNTe\FilamentFlow\Definition\ValidationRule;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * A transition: its formula condition, its side effects, and an action — which moves no state.
 */
class TransitionTest extends TestCase
{
    public function test_formula_condition_and_side_effects(): void
    {
        $transition = Transition::make('submit', 'draft', 'submitted')
            ->label('Submit')
            ->confirm()
            ->formulaCondition('amount <= 100', 'Too much.')
            ->sideEffect(SideEffect::setTimestamp('meta.submitted_at', 'now'))
            ->validationRule(ValidationRule::make('score')->rules(['gte:60'])->message('Min 60.'));

        $data = $transition->toArray();

        $this->assertSame('Submit', $data['label']);
        $this->assertTrue($data['requires_confirmation']);
        $this->assertSame('formula', $data['conditions'][0]['type']);
        $this->assertSame('amount <= 100', $data['conditions'][0]['expression']);
        $this->assertSame('Too much.', $data['conditions'][0]['message_template']);
        $this->assertCount(1, $transition->effects());
        $this->assertCount(1, $transition->rules());
    }

    public function test_action_has_no_state_change(): void
    {
        $action = Transition::action('recalculate', 'Recalculate');

        $this->assertNull($action->from());
        $this->assertNull($action->to());
        $this->assertSame('Recalculate', $action->toArray()['label']);
    }
}
