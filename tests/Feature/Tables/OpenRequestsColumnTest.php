<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Tables;

use Illuminate\Support\Carbon;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Tables\Columns\OpenRequestsColumn;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * The column that tells, at a glance, that a row awaits an answer — the request an earlier
 * transition opened and no later one answered, read from the same history the entry reads.
 */
class OpenRequestsColumnTest extends TestCase
{
    public function test_the_column_carries_a_translated_label_and_is_toggleable(): void
    {
        $column = OpenRequestsColumn::make();

        $this->assertSame(__('filament-flow::messages.open_requests_label'), $column->getLabel());
        $this->assertTrue($column->isToggleable());
    }

    public function test_a_row_awaiting_an_answer_reads_the_newest_request(): void
    {
        [$order] = $this->requested();

        $request = OpenRequestsColumn::make('open_requests')->getRequest($order);

        $this->assertNotNull($request);
        $this->assertTrue($request->isOpen());
        $this->assertSame('request_integration', $request->transitionName);
        $this->assertSame('Serve la marca da bollo', $request->note);
        $this->assertNotNull($request->deadline);
    }

    public function test_a_row_with_no_open_request_reads_nothing(): void
    {
        [$order] = $this->requested(answered: true);

        $this->assertNull(OpenRequestsColumn::make('open_requests')->getRequest($order));
    }

    /**
     * @return array{0: Order}
     */
    private function requested(bool $answered = false): array
    {
        $workflow = $this->createTestWorkflow();

        $underReview = $this->createWorkflowState($workflow, ['name' => 'under_review', 'sort_order' => 0]);
        $requestedState = $this->createWorkflowState($workflow, ['name' => 'integration_requested', 'sort_order' => 1]);

        $request = $this->createWorkflowTransition($workflow, $underReview, $requestedState, [
            'name' => 'request_integration',
            'label' => 'Request an integration',
            'metadata' => [
                'opens_request' => true,
                'note_field' => 'review.notes',
                'deadline_field' => 'meta.deadline',
            ],
        ]);

        $answer = $this->createWorkflowTransition($workflow, $requestedState, $underReview, [
            'name' => 'resubmit',
            'label' => 'Send the integration',
            'metadata' => ['answers_request' => true],
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ORC-001',
            'customer_name' => 'Test Customer',
            'total_amount' => 50.00,
            'state' => 'integration_requested',
            'form_data' => [
                'review' => ['notes' => 'Serve la marca da bollo'],
                'meta' => ['deadline' => Carbon::now()->addDays(4)->toDateTimeString()],
            ],
        ]);

        $this->log($order, $request, 'under_review', 'integration_requested', '2026-03-08 09:00:00');

        if ($answered) {
            $this->log($order, $answer, 'integration_requested', 'under_review', '2026-03-09 09:00:00');
        }

        return [$order];
    }

    private function log(Order $order, WorkflowTransition $transition, string $from, string $to, string $at): void
    {
        // `created_at` is not fillable: the row is filled by force so the history can be laid
        // out in time.
        $row = new WorkflowStateTransition;
        $row->forceFill([
            'transitionable_type' => Order::class,
            'transitionable_id' => $order->id,
            'workflow_id' => $transition->workflow_id,
            'transition_id' => $transition->id,
            'from_state' => $from,
            'to_state' => $to,
            'from_state_label' => $from,
            'to_state_label' => $to,
            'is_visible' => true,
            'created_at' => Carbon::parse($at),
        ]);
        $row->save();
    }
}
