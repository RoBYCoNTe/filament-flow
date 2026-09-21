<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Definition;

use Illuminate\Support\Facades\DB;
use RoBYCoNTe\FilamentFlow\Definition\Enums\MutationClass;
use RoBYCoNTe\FilamentFlow\Definition\State;
use RoBYCoNTe\FilamentFlow\Definition\Transition;
use RoBYCoNTe\FilamentFlow\Definition\WorkflowApplier;
use RoBYCoNTe\FilamentFlow\Definition\WorkflowDefinition;
use RoBYCoNTe\FilamentFlow\Exceptions\WorkflowConflictException;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

class WorkflowApplierTest extends TestCase
{
    private const TENANT = 7;

    private function definition(): WorkflowDefinition
    {
        return WorkflowDefinition::make('order', Order::class)
            ->state(State::make('draft', 'Draft')->initial()->color('gray'))
            ->state(State::make('paid', 'Paid')->final()->color('success'))
            ->transition(Transition::make('pay', 'draft', 'paid')->label('Pay'));
    }

    private function applier(): WorkflowApplier
    {
        return app(WorkflowApplier::class);
    }

    public function test_persists_states_and_transitions_scoped_by_tenant(): void
    {
        $workflow = $this->applier()->apply(Order::class, self::TENANT, $this->definition());

        $this->assertSame(self::TENANT, $workflow->tenant_id);
        $this->assertSame(Order::class, $workflow->model_type);
        $this->assertSame(2, WorkflowState::where('workflow_id', $workflow->id)->count());
        $this->assertSame(1, WorkflowTransition::where('workflow_id', $workflow->id)->count());
    }

    public function test_is_idempotent(): void
    {
        $first = $this->applier()->apply(Order::class, self::TENANT, $this->definition());
        $second = $this->applier()->apply(Order::class, self::TENANT, $this->definition());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Workflow::where('tenant_id', self::TENANT)->count());
        $this->assertSame(2, WorkflowState::where('workflow_id', $first->id)->count());
        $this->assertSame(1, $first->fresh()->schema_version);
        $this->assertSame(0, DB::table('workflow_snapshots')->where('workflow_id', $first->id)->count());
    }

    public function test_snapshots_on_breaking_change(): void
    {
        $workflow = $this->applier()->apply(Order::class, self::TENANT, $this->definition());

        // Remove the "paid" state and its transition (a breaking change).
        $desired = WorkflowDefinition::make('order', Order::class)
            ->state(State::make('draft', 'Draft')->initial()->color('gray'));

        $this->applier()->apply(Order::class, self::TENANT, $desired);

        $workflow->refresh();

        $this->assertSame(2, $workflow->schema_version);
        $this->assertSame(1, DB::table('workflow_snapshots')->where('workflow_id', $workflow->id)->count());
        $this->assertSame(1, WorkflowState::where('workflow_id', $workflow->id)->count());
        $this->assertSame(0, WorkflowTransition::where('workflow_id', $workflow->id)->count());
    }

    public function test_snapshot_uses_the_provided_revision_version(): void
    {
        $workflow = $this->applier()->apply(Order::class, self::TENANT, $this->definition());

        // Remove the "paid" state and its transition (breaking), forcing the
        // snapshot at an explicit revision version (e.g. the bando version).
        $desired = WorkflowDefinition::make('order', Order::class)
            ->state(State::make('draft', 'Draft')->initial()->color('gray'));

        $this->applier()->apply(Order::class, self::TENANT, $desired, revisionVersion: 5);

        $workflow->refresh();

        $this->assertSame(6, $workflow->schema_version);
        $this->assertSame(
            1,
            DB::table('workflow_snapshots')->where('workflow_id', $workflow->id)->where('version', 5)->count(),
        );
    }

    public function test_rolls_back_on_conflict(): void
    {
        $workflow = $this->applier()->apply(Order::class, self::TENANT, $this->definition());

        // The "pay" transition survives but its "draft" state is removed -> state_in_use.
        $desired = WorkflowDefinition::make('order', Order::class)
            ->state(State::make('paid', 'Paid')->final()->color('success'))
            ->transition(Transition::make('pay', null, 'paid')->label('Pay'));

        try {
            $this->applier()->apply(Order::class, self::TENANT, $desired);
            $this->fail('Expected WorkflowConflictException.');
        } catch (WorkflowConflictException $e) {
            $this->assertSame('state_in_use', $e->conflicts()[0]['code']);
            $this->assertSame(MutationClass::Breaking, $e->plan->mutationClass());
        }

        // Nothing was written.
        $this->assertSame(2, WorkflowState::where('workflow_id', $workflow->id)->count());
        $this->assertSame(1, WorkflowTransition::where('workflow_id', $workflow->id)->count());
        $this->assertSame(1, $workflow->fresh()->schema_version);
    }
}
