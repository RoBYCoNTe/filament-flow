<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Planning;

use RoBYCoNTe\FilamentFlow\Definition\Enums\MutationClass;
use RoBYCoNTe\FilamentFlow\Definition\Planning\PlanOptions;
use RoBYCoNTe\FilamentFlow\Definition\Planning\WorkflowPlanner;
use RoBYCoNTe\FilamentFlow\Definition\State;
use RoBYCoNTe\FilamentFlow\Definition\Transition;
use RoBYCoNTe\FilamentFlow\Definition\WorkflowDefinition;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class WorkflowPlannerTest extends TestCase
{
    private function definition(): WorkflowDefinition
    {
        return WorkflowDefinition::make('order', Order::class)
            ->state(State::make('draft', 'Draft')->initial()->color('gray'))
            ->state(State::make('paid', 'Paid')->final()->color('success'))
            ->transition(Transition::make('pay', 'draft', 'paid')->label('Pay'));
    }

    /** Persists a workflow whose rows match the given definition. */
    private function persist(WorkflowDefinition $definition): Workflow
    {
        $workflow = Workflow::create([
            'name' => $definition->getName(),
            'model_type' => $definition->getModelType(),
            'state_column' => $definition->getStateColumn(),
            'is_active' => true,
        ]);

        $ids = [];

        foreach ($definition->getStates() as $index => $state) {
            $data = $state->toArray();

            $row = WorkflowState::create([
                'workflow_id' => $workflow->id,
                'name' => $data['name'],
                'label' => $data['label'],
                'color' => $data['color'],
                'sort_order' => $index,
                'is_initial' => $data['is_initial'],
                'is_final' => $data['is_final'],
            ]);

            $ids[$state->name()] = $row->id;
        }

        foreach ($definition->getTransitions() as $transition) {
            $data = $transition->toArray();

            WorkflowTransition::create([
                'workflow_id' => $workflow->id,
                'name' => $data['name'],
                'label' => $data['label'],
                'from_state_id' => $data['from'] !== null ? ($ids[$data['from']] ?? null) : null,
                'to_state_id' => $data['to'] !== null ? ($ids[$data['to']] ?? null) : null,
                'requires_confirmation' => $data['requires_confirmation'],
                'requires_reason' => $data['requires_reason'],
                'conditions' => $data['conditions'] ?: null,
                'metadata' => $data['metadata'] ?: null,
            ]);
        }

        return $workflow;
    }

    public function test_plan_is_clean_when_nothing_changes(): void
    {
        $definition = $this->definition();
        $workflow = $this->persist($definition);

        $plan = (new WorkflowPlanner)->plan($definition, $workflow);

        $this->assertTrue($plan->isClean());
        $this->assertTrue($plan->isApplicable());
        $this->assertNull($plan->mutationClass());
    }

    public function test_added_state_is_additive(): void
    {
        $base = $this->definition();
        $workflow = $this->persist($base);

        $plan = (new WorkflowPlanner)->plan($base->state(State::make('cancelled', 'Cancelled')), $workflow);

        $added = $plan->changesOf('added_state');
        $this->assertCount(1, $added);
        $this->assertSame('cancelled', $added[0]->key);
        $this->assertSame(MutationClass::Additive, $added[0]->mutationClass);
    }

    public function test_unknown_state_is_rejected(): void
    {
        $definition = WorkflowDefinition::make('order', Order::class)
            ->state(State::make('draft', 'Draft')->initial())
            ->transition(Transition::make('pay', 'draft', 'paid'));

        $plan = (new WorkflowPlanner)->plan($definition);

        $this->assertFalse($plan->isApplicable());
        $this->assertSame('unknown_state', $plan->conflicts[0]['code']);
        $this->assertSame('pay', $plan->conflicts[0]['key']);
    }

    public function test_removed_state_referenced_by_transition_conflicts(): void
    {
        $base = $this->definition();
        $workflow = $this->persist($base);

        // The transition survives but becomes global, while its old "draft" state is removed.
        $desired = WorkflowDefinition::make('order', Order::class)
            ->state(State::make('paid', 'Paid')->final()->color('success'))
            ->transition(Transition::make('pay', null, 'paid')->label('Pay'));

        $plan = (new WorkflowPlanner)->plan($desired, $workflow);

        $removed = $plan->changesOf('removed_state');
        $this->assertCount(1, $removed);
        $this->assertSame(MutationClass::Breaking, $removed[0]->mutationClass);

        $this->assertFalse($plan->isApplicable());
        $this->assertSame('state_in_use', $plan->conflicts[0]['code']);
        $this->assertSame('draft', $plan->conflicts[0]['key']);
    }

    public function test_force_allows_removing_a_referenced_state(): void
    {
        $base = $this->definition();
        $workflow = $this->persist($base);

        $desired = WorkflowDefinition::make('order', Order::class)
            ->state(State::make('paid', 'Paid')->final()->color('success'))
            ->transition(Transition::make('pay', null, 'paid')->label('Pay'));

        $plan = (new WorkflowPlanner)->plan($desired, $workflow, PlanOptions::make()->force());

        $this->assertTrue($plan->isApplicable());
        $this->assertSame(MutationClass::Breaking, $plan->mutationClass());
    }
}
