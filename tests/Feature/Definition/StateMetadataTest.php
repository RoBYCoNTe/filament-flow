<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Definition;

use RoBYCoNTe\FilamentFlow\Definition\Planning\WorkflowPlanner;
use RoBYCoNTe\FilamentFlow\Definition\State;
use RoBYCoNTe\FilamentFlow\Definition\WorkflowApplier;
use RoBYCoNTe\FilamentFlow\Definition\WorkflowDefinition;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * The metadata of a state is part of the definition: the planner must see it change, or a
 * deployed workflow would keep the old value for ever.
 */
class StateMetadataTest extends TestCase
{
    public function test_the_metadata_of_a_state_is_persisted(): void
    {
        $workflow = $this->apply($this->definition('assessment'));

        $this->assertSame(
            ['phase' => 'assessment'],
            WorkflowState::where('workflow_id', $workflow->id)->where('name', 'under_review')->firstOrFail()->metadata,
        );
    }

    public function test_the_planner_detects_a_changed_metadata(): void
    {
        $workflow = $this->apply($this->definition('assessment'));

        $plan = (new WorkflowPlanner)->plan($this->definition('grant'), $workflow->refresh());

        $this->assertNotEmpty($plan->changesOf('updated_state'));
    }

    public function test_the_planner_detects_an_added_metadata(): void
    {
        $workflow = $this->apply($this->definition(null));

        $plan = (new WorkflowPlanner)->plan($this->definition('assessment'), $workflow->refresh());

        $this->assertNotEmpty($plan->changesOf('updated_state'));
    }

    public function test_reapplying_the_same_metadata_is_a_noop(): void
    {
        $workflow = $this->apply($this->definition('assessment'));

        $plan = (new WorkflowPlanner)->plan($this->definition('assessment'), $workflow->refresh());

        $this->assertEmpty($plan->changesOf('updated_state'));
    }

    private function apply(WorkflowDefinition $definition): Workflow
    {
        return app(WorkflowApplier::class)->apply(Order::class, null, $definition);
    }

    private function definition(?string $phase): WorkflowDefinition
    {
        return WorkflowDefinition::make('orders-metadata', Order::class)
            ->state(State::make('draft', 'Draft')->initial())
            ->state(State::make('under_review', 'Under review')->metadata($phase === null ? [] : ['phase' => $phase]))
            ->state(State::make('approved', 'Approved')->final());
    }
}
