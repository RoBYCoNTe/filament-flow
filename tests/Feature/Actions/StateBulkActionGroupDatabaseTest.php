<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Actions;

use Filament\Actions\BulkAction;
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
}
