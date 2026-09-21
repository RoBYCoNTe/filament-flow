<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Definition;

use Illuminate\Support\Facades\DB;
use RoBYCoNTe\FilamentFlow\Definition\Enums\MutationClass;
use RoBYCoNTe\FilamentFlow\Definition\Planning\WorkflowPlanner;
use RoBYCoNTe\FilamentFlow\Definition\RoleOverride;
use RoBYCoNTe\FilamentFlow\Definition\State;
use RoBYCoNTe\FilamentFlow\Definition\StateField;
use RoBYCoNTe\FilamentFlow\Definition\WorkflowApplier;
use RoBYCoNTe\FilamentFlow\Definition\WorkflowDefinition;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateField;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateFieldRole;
use RoBYCoNTe\FilamentFlow\Services\WorkflowFieldPermissionsService;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Role overrides declared in the Definition SDK: they are written by the
 * applier, diffed by the planner, restored from an exported array and included
 * in the revision snapshot.
 */
class StateFieldRoleOverrideTest extends TestCase
{
    public function test_applier_persists_role_overrides(): void
    {
        $workflow = $this->apply($this->definitionWithOverrides());

        $field = $this->stateField($workflow, 'costs.amount');
        $overrides = WorkflowStateFieldRole::where('state_field_id', $field->id)->orderBy('role_name')->get();

        $this->assertSame(['admin', 'reviewer'], $overrides->pluck('role_name')->all());

        $reviewer = $overrides->firstWhere('role_name', 'reviewer');
        $this->assertSame('editable', $reviewer->mutability);
        $this->assertNull($reviewer->visibility);
        $this->assertNull($reviewer->is_required);

        $admin = $overrides->firstWhere('role_name', 'admin');
        $this->assertSame('visible', $admin->visibility);
        $this->assertSame('editable', $admin->mutability);
        $this->assertTrue($admin->is_required);
    }

    public function test_role_overrides_are_effective_at_runtime(): void
    {
        $workflow = $this->apply($this->definitionWithOverrides());
        $state = WorkflowState::where('workflow_id', $workflow->id)->where('name', 'under_review')->firstOrFail();

        $order = Order::create([
            'order_number' => 'ORD-ROLE',
            'customer_name' => 'Test',
            'total_amount' => 10,
            'state' => $state->name,
        ]);

        $reviewer = $this->createTestUser(['email' => 'reviewer@test.com', 'role' => 'reviewer']);
        $other = $this->createTestUser(['email' => 'other@test.com', 'role' => 'applicant']);

        $service = app(WorkflowFieldPermissionsService::class);

        // The parent rule locks the whole repeater, the reviewer override unlocks one column.
        $this->assertFalse($service->permissionFor($order, 'costs.amount', $reviewer)['locked']);
        $this->assertTrue($service->permissionFor($order, 'costs.amount', $other)['locked']);

        // The override only touches the declared attributes.
        $this->assertTrue($service->permissionFor($order, 'costs.amount', $reviewer)['visible']);
    }

    public function test_planner_detects_an_added_role_override(): void
    {
        $workflow = $this->apply($this->definition());

        $plan = (new WorkflowPlanner)->plan($this->definitionWithOverrides(), $workflow->refresh());

        $this->assertFalse($plan->isClean());
        $this->assertSame(MutationClass::Safe, $plan->mutationClass());
        $this->assertNotEmpty($plan->changesOf('updated_state_fields'));
    }

    public function test_planner_detects_a_removed_role_override(): void
    {
        $workflow = $this->apply($this->definitionWithOverrides());

        $plan = (new WorkflowPlanner)->plan($this->definition(), $workflow->refresh());

        $this->assertFalse($plan->isClean());
        $this->assertNotEmpty($plan->changesOf('updated_state_fields'));
    }

    public function test_reapplying_the_same_definition_is_a_noop(): void
    {
        $workflow = $this->apply($this->definitionWithOverrides());

        $plan = (new WorkflowPlanner)->plan($this->definitionWithOverrides(), $workflow->refresh());

        $this->assertTrue($plan->isClean(), 'Re-applying must not report changes: '.$plan->summary());
    }

    public function test_planner_is_idempotent_with_validation_rules(): void
    {
        $definition = $this->definition()->state(
            State::make('under_review', 'Under review')->fields([
                StateField::make('costs')->locked()->validationRules(['gte:0', 'max:100000']),
            ]),
        );

        $workflow = $this->apply($definition);
        $plan = (new WorkflowPlanner)->plan($definition, $workflow->refresh());

        $this->assertTrue(
            $plan->isClean(),
            'State fields with validation rules must compare as unchanged: '.$plan->summary(),
        );
    }

    public function test_round_trip_keeps_role_overrides(): void
    {
        $field = StateField::make('costs.amount')
            ->locked()
            ->validationRules(['gte:0'])
            ->forRole('reviewer', fn (RoleOverride $role) => $role->editable())
            ->forRole('admin', fn (RoleOverride $role) => $role->visible()->required());

        $restored = StateField::fromArray($field->toArray());

        $this->assertSame($field->toArray(), $restored->toArray());
        $this->assertSame(['admin', 'reviewer'], array_map(
            static fn ($override): string => $override->roleName(),
            $restored->roleOverrides(),
        ));
    }

    public function test_snapshot_includes_role_overrides(): void
    {
        $workflow = $this->apply($this->definitionWithOverrides());

        // Removing a state is a breaking change, so the current rows are snapshotted first.
        $this->apply(WorkflowDefinition::make('orders-roles', Order::class)
            ->state(State::make('draft', 'Draft')->initial())
            ->state(State::make('under_review', 'Under review')->fields([
                StateField::make('costs')->locked(),
                StateField::make('costs.amount')->locked(),
            ])));

        $snapshot = DB::table('workflow_snapshots')
            ->where('workflow_id', $workflow->id)
            ->orderByDesc('version')
            ->first();

        $this->assertNotNull($snapshot, 'A breaking change must create a revision snapshot.');

        $payload = json_decode((string) $snapshot->snapshot, true);

        $this->assertArrayHasKey('state_field_roles', $payload);
        $this->assertContains('reviewer', array_column($payload['state_field_roles'], 'role_name'));
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function apply(WorkflowDefinition $definition): Workflow
    {
        return app(WorkflowApplier::class)->apply(Order::class, null, $definition);
    }

    private function stateField(Workflow $workflow, string $name): WorkflowStateField
    {
        $state = WorkflowState::where('workflow_id', $workflow->id)->where('name', 'under_review')->firstOrFail();

        return WorkflowStateField::where('state_id', $state->id)->where('field_name', $name)->firstOrFail();
    }

    private function definition(): WorkflowDefinition
    {
        return WorkflowDefinition::make('orders-roles', Order::class)
            ->state(State::make('draft', 'Draft')->initial())
            ->state(State::make('under_review', 'Under review')->fields([
                StateField::make('costs')->locked(),
                StateField::make('costs.amount')->locked(),
            ]))
            ->state(State::make('approved', 'Approved')->final());
    }

    private function definitionWithOverrides(): WorkflowDefinition
    {
        return WorkflowDefinition::make('orders-roles', Order::class)
            ->state(State::make('draft', 'Draft')->initial())
            ->state(State::make('under_review', 'Under review')->fields([
                StateField::make('costs')->locked(),
                StateField::make('costs.amount')
                    ->locked()
                    ->forRole('reviewer', fn (RoleOverride $role) => $role->editable())
                    ->forRole('admin', fn (RoleOverride $role) => $role->visible()->editable()->required()),
            ]))
            ->state(State::make('approved', 'Approved')->final());
    }
}
