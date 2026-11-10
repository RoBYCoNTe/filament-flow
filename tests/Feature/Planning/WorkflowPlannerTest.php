<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Planning;

use Illuminate\Support\Facades\DB;
use RoBYCoNTe\FilamentFlow\Definition\Enums\MutationClass;
use RoBYCoNTe\FilamentFlow\Definition\Planning\PlanOptions;
use RoBYCoNTe\FilamentFlow\Definition\Planning\WorkflowPlanner;
use RoBYCoNTe\FilamentFlow\Definition\SideEffect;
use RoBYCoNTe\FilamentFlow\Definition\State;
use RoBYCoNTe\FilamentFlow\Definition\Transition;
use RoBYCoNTe\FilamentFlow\Definition\ValidationRule;
use RoBYCoNTe\FilamentFlow\Definition\WorkflowDefinition;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionSideEffect;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionValidationRule;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * The plan of a workflow: nothing to do when nothing changed, an added state as additive, an
 * unknown state refused, the removal of a state that transitions still point at as a conflict —
 * and the force that allows it anyway.
 */
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

    /**
     * A transition that checks one field three times and sets another one twice: the rules and the
     * effects share a field name, so only their order tells them apart.
     */
    private function definitionWithRepeatedFields(): WorkflowDefinition
    {
        return WorkflowDefinition::make('order', Order::class)
            ->state(State::make('draft', 'Draft')->initial()->color('gray'))
            ->state(State::make('paid', 'Paid')->final()->color('success'))
            ->transition(
                Transition::make('pay', 'draft', 'paid')
                    ->label('Pay')
                    ->validationRule(ValidationRule::make('amount')->rules('required')->message('Amount first.'))
                    ->validationRule(ValidationRule::make('amount')->rules('numeric')->message('Amount second.'))
                    ->validationRule(ValidationRule::make('amount')->rules('min:1')->message('Amount third.'))
                    ->validationRule(ValidationRule::make('notes')->rules('required'))
                    ->sideEffect(SideEffect::setField('stage', '"one"'))
                    ->sideEffect(SideEffect::setField('stage', '"two"'))
                    ->sideEffect(SideEffect::setField('stage', '"three"')),
            );
    }

    /**
     * Persists the rules and the effects of the `pay` transition in the given physical order of
     * insertion — the order a database may hand rows back in when nothing sorts them — each
     * with the `sort_order` its position in the declaration gives it.
     *
     * @param  list<int>  $insertionOrder  positions in the declaration, in the order they are inserted
     */
    private function persistDetails(WorkflowDefinition $definition, Workflow $workflow, array $insertionOrder): void
    {
        $transition = WorkflowTransition::query()->where('workflow_id', $workflow->id)->where('name', 'pay')->firstOrFail();
        $declared = collect($definition->getTransitions())->first(fn (Transition $t): bool => $t->name() === 'pay');

        foreach ($insertionOrder as $position) {
            if (isset($declared->rules()[$position])) {
                $attributes = $declared->rules()[$position]->toArray();
                unset($attributes['field_type']);

                WorkflowTransitionValidationRule::create(['sort_order' => $position] + $attributes + ['transition_id' => $transition->id]);
            }

            if (isset($declared->effects()[$position])) {
                WorkflowTransitionSideEffect::create(['sort_order' => $position] + $declared->effects()[$position]->toArray() + ['transition_id' => $transition->id]);
            }
        }
    }

    public function test_rules_and_effects_sharing_a_field_are_not_a_change_whatever_order_the_rows_come_back_in(): void
    {
        $definition = $this->definitionWithRepeatedFields();
        $workflow = $this->persist($definition);

        // Inserted scrambled, the way a database that reuses freed space hands them back.
        $this->persistDetails($definition, $workflow, [2, 3, 0, 1]);

        $plan = (new WorkflowPlanner)->plan($definition, $workflow);

        $this->assertSame([], $plan->changesOf('updated_transition'));
        $this->assertTrue($plan->isClean());
    }

    public function test_reordering_rules_that_share_a_field_is_a_change(): void
    {
        $definition = $this->definitionWithRepeatedFields();
        $workflow = $this->persist($definition);
        $this->persistDetails($definition, $workflow, [0, 1, 2, 3]);

        // The order is what the engine follows: swapping two checks of the same field is a change.
        $first = WorkflowTransitionValidationRule::query()->where('sort_order', 0)->firstOrFail();
        $second = WorkflowTransitionValidationRule::query()->where('sort_order', 1)->firstOrFail();
        $first->update(['sort_order' => 1]);
        $second->update(['sort_order' => 0]);

        $plan = (new WorkflowPlanner)->plan($definition, $workflow);

        $this->assertCount(1, $plan->changesOf('updated_transition'));
    }

    /**
     * The reading that feeds the comparison must say its order: without an `ORDER BY` PostgreSQL
     * hands rows back in their physical order, which changes whenever space is reused, and rows
     * that share a field name — the order is all that tells them apart — look reordered, so the
     * transition is "changed" again after every apply. SQLite hides it by reading through the
     * index, so the order is asserted on the query itself.
     */
    public function test_the_rows_that_feed_the_comparison_are_read_in_declared_order(): void
    {
        $definition = $this->definitionWithRepeatedFields();
        $workflow = $this->persist($definition);
        $this->persistDetails($definition, $workflow, [0, 1, 2, 3]);

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        (new WorkflowPlanner)->plan($definition, $workflow);

        foreach (['workflow_transition_validation_rules', 'workflow_transition_side_effects'] as $table) {
            $reads = array_filter($queries, fn (string $sql): bool => str_contains($sql, 'from "'.$table.'"'));

            $this->assertNotEmpty($reads, "The plan did not read [{$table}].");

            foreach ($reads as $sql) {
                $this->assertMatchesRegularExpression('/order by "sort_order" asc, "id" asc/', $sql, "The read of [{$table}] has no declared order: {$sql}");
            }
        }
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
