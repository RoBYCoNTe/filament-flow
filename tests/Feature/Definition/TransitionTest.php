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

    public function test_a_transition_may_open_and_answer_a_request(): void
    {
        $request = Transition::make('request_integration', 'draft', 'integration_requested')
            ->opensRequest();
        $answer = Transition::make('resubmit', 'integration_requested', 'draft')
            ->answersRequest();

        $this->assertTrue($request->isRequestOpening());
        $this->assertFalse($request->isRequestAnswering());
        $this->assertSame(['opens_request' => true], $request->toArray()['metadata']);

        $this->assertTrue($answer->isRequestAnswering());
        $this->assertFalse($answer->isRequestOpening());
        $this->assertSame(['answers_request' => true], $answer->toArray()['metadata']);
    }

    public function test_the_request_flags_keep_the_metadata_a_transition_already_carries(): void
    {
        $transition = Transition::make('request', 'a', 'b')
            ->metadata(['note' => 'kept'])
            ->opensRequest()
            ->answersRequest();

        $metadata = $transition->toArray()['metadata'];

        $this->assertSame('kept', $metadata['note']);
        $this->assertTrue($metadata['opens_request']);
        $this->assertTrue($metadata['answers_request']);
    }

    public function test_a_request_declares_where_its_note_and_term_live(): void
    {
        $transition = Transition::make('request_integration', 'review', 'integration')
            ->opensRequest()
            ->withRequestFields(noteField: 'review.notes', deadlineField: 'meta.deadline');

        $metadata = $transition->toArray()['metadata'];

        $this->assertTrue($metadata['opens_request']);
        $this->assertSame('review.notes', $metadata['note_field']);
        $this->assertSame('meta.deadline', $metadata['deadline_field']);
    }

    public function test_a_transition_may_leave_a_message_with_its_note(): void
    {
        $reject = Transition::make('reject', 'review', 'rejected')
            ->leavesMessage('rejection_reason');

        $metadata = $reject->toArray()['metadata'];

        $this->assertTrue($reject->isMessageLeaving());
        $this->assertFalse($reject->isRequestOpening());
        $this->assertSame('rejection_reason', $metadata['note_field']);
    }
}
