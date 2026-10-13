<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Actions;

use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use RoBYCoNTe\FilamentFlow\Actions\StateBulkActionGroup;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateField;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Bulk transitions of a database-first workflow: every record is validated
 * individually, the ones that fail are reported instead of silently skipped.
 */
class StateBulkActionGroupDatabaseTest extends TestCase
{
    public function test_bulk_actions_are_generated_for_every_transition(): void
    {
        $this->workflow();

        $actions = StateBulkActionGroup::forDatabaseRecord(Order::class, 'state');

        $this->assertCount(1, $actions);
        $this->assertInstanceOf(BulkAction::class, $actions[0]);
    }

    public function test_only_the_named_transitions_are_offered(): void
    {
        $this->multiWorkflow();

        $names = array_map(
            static fn (BulkAction $action): string => $action->getName(),
            StateBulkActionGroup::forDatabaseRecord(Order::class, 'state', null, ['ship']),
        );

        $this->assertSame(['transition-ship'], $names);
    }

    public function test_an_empty_list_offers_nothing(): void
    {
        $this->multiWorkflow();

        $this->assertSame([], StateBulkActionGroup::forDatabaseRecord(Order::class, 'state', null, []));
    }

    public function test_the_transitions_that_read_the_same_are_one_action(): void
    {
        $this->multiWorkflow();

        // `ship` and `ship_late` share label and target: one action. `cancel` is another.
        $actions = StateBulkActionGroup::forDatabaseRecord(Order::class, 'state');

        $this->assertCount(2, $actions);
        $this->assertSame(['transition-ship', 'transition-cancel'], array_map(
            static fn (BulkAction $action): string => $action->getName(),
            $actions,
        ));
    }

    public function test_a_record_takes_the_transition_available_to_it_and_the_others_are_left_alone(): void
    {
        $this->multiWorkflow();

        $pending = Order::create(['order_number' => 'A', 'customer_name' => 'Mario', 'customer_email' => 'm@example.test', 'total_amount' => 10, 'state' => 'pending']);
        $packed = Order::create(['order_number' => 'B', 'customer_name' => 'Luisa', 'customer_email' => 'l@example.test', 'total_amount' => 10, 'state' => 'packed']);
        $cancelled = Order::create(['order_number' => 'C', 'customer_name' => 'Gino', 'customer_email' => 'g@example.test', 'total_amount' => 10, 'state' => 'cancelled']);

        $ship = StateBulkActionGroup::forDatabaseRecord(Order::class, 'state', null, ['ship', 'ship_late'])[0];

        $ship->call(['records' => new Collection([$pending, $packed, $cancelled])]);

        // `ship` from pending and `ship_late` from packed are the same action; a cancelled
        // order is not in a state it applies to and is left as it is.
        $this->assertSame('shipped', $pending->fresh()->state);
        $this->assertSame('shipped', $packed->fresh()->state);
        $this->assertSame('cancelled', $cancelled->fresh()->state);
    }

    // ── fixture ──────────────────────────────────────────────────────────────

    private function workflow(): void
    {
        $workflow = $this->createTestWorkflow();

        $pending = $this->createWorkflowState($workflow, ['name' => 'pending', 'is_initial' => true]);
        $shipped = $this->createWorkflowState($workflow, ['name' => 'shipped']);

        $this->createWorkflowTransition($workflow, $pending, $shipped, ['name' => 'ship', 'label' => 'Ship']);

        // `pending` requires the tracking number: the second record cannot ship.
        WorkflowStateField::create([
            'state_id' => $pending->id,
            'field_name' => 'tracking_number',
            'visibility' => 'visible',
            'mutability' => 'editable',
            'is_required' => true,
        ]);
    }

    private function multiWorkflow(): void
    {
        $workflow = $this->createTestWorkflow();

        $pending = $this->createWorkflowState($workflow, ['name' => 'pending', 'is_initial' => true]);
        $packed = $this->createWorkflowState($workflow, ['name' => 'packed']);
        $shipped = $this->createWorkflowState($workflow, ['name' => 'shipped']);
        $cancelled = $this->createWorkflowState($workflow, ['name' => 'cancelled']);

        $this->createWorkflowTransition($workflow, $pending, $shipped, ['name' => 'ship', 'label' => 'Ship']);
        $this->createWorkflowTransition($workflow, $packed, $shipped, ['name' => 'ship_late', 'label' => 'Ship']);
        $this->createWorkflowTransition($workflow, $pending, $cancelled, ['name' => 'cancel', 'label' => 'Cancel']);
    }
}
