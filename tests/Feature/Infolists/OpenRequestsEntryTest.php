<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Infolists;

use Illuminate\Support\Carbon;
use RoBYCoNTe\FilamentFlow\Infolists\Components\OpenRequestsEntry;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransition;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * What the workflow waits for: the exchange a transition opened and no later one answered,
 * read from the history the engine already keeps — the note and the term among the paths of the
 * host, nothing stored for it.
 */
class OpenRequestsEntryTest extends TestCase
{
    public function test_the_entry_carries_a_translated_label(): void
    {
        $this->assertSame(
            __('filament-flow::messages.open_requests_label'),
            OpenRequestsEntry::make()->getLabel(),
        );
    }

    public function test_a_transition_flagged_as_a_request_reads_as_an_exchange(): void
    {
        [$order] = $this->exchange(answered: false);

        $requests = $this->entry()->requestsFor($order);

        $this->assertCount(1, $requests);
        $this->assertTrue($requests->first()->isOpen());
        $this->assertSame('request_integration', $requests->first()->transitionName);
        $this->assertSame('Request an integration', $requests->first()->label);
        $this->assertSame('integration_requested', $requests->first()->toState);
    }

    public function test_an_answer_closes_the_exchange_and_takes_it_out_of_the_open_ones(): void
    {
        [$order] = $this->exchange(answered: true);

        $this->assertCount(0, $this->entry()->requestsFor($order));

        $all = $this->entry()->showAnswered()->requestsFor($order);

        $this->assertCount(1, $all);
        $this->assertFalse($all->first()->isOpen());
        $this->assertSame('resubmit', $all->first()->answeredByTransition);
        $this->assertNotNull($all->first()->answeredAt);
    }

    public function test_a_workflow_that_marks_no_transition_stays_silent(): void
    {
        $workflow = $this->createTestWorkflow();

        $draft = $this->createWorkflowState($workflow, ['name' => 'draft', 'is_initial' => true]);
        $review = $this->createWorkflowState($workflow, ['name' => 'review']);

        $transition = $this->createWorkflowTransition($workflow, $draft, $review, ['name' => 'send']);

        $order = Order::create([
            'order_number' => 'ORD-ORE-001',
            'customer_name' => 'Test Customer',
            'total_amount' => 10.00,
            'state' => 'draft',
        ]);

        $this->logTransition($order, $transition, 'draft', 'review');

        $this->assertCount(0, $this->entry()->requestsFor($order));
    }

    public function test_the_host_may_name_the_transitions_instead_of_marking_them(): void
    {
        [$order] = $this->exchange(answered: false, markWithDsl: false);

        $this->assertCount(0, $this->entry()->requestsFor($order));

        $named = $this->entry()
            ->transitions(['request_integration'], ['resubmit'])
            ->requestsFor($order);

        $this->assertCount(1, $named);
        $this->assertTrue($named->first()->isOpen());
    }

    public function test_the_note_comes_from_the_path_of_the_host_first(): void
    {
        [$order] = $this->exchange(answered: false);

        $request = $this->entry()->noteField('review.notes')->requestsFor($order)->first();

        $this->assertSame('Serve la marca da bollo', $request->note);
    }

    public function test_the_note_falls_back_to_the_column_of_the_engine(): void
    {
        [$order, $open] = $this->exchange(answered: false);

        $open->update(['notes' => 'Nota scritta nella history']);

        $request = $this->entry()->noteField('review.missing')->requestsFor($order)->first();

        $this->assertSame('Nota scritta nella history', $request->note);
    }

    public function test_the_term_is_read_and_measured_from_the_record(): void
    {
        $deadline = Carbon::now()->addDays(2)->startOfDay();

        [$order] = $this->exchange(answered: false, deadline: $deadline);

        $request = $this->entry()->deadlineField('meta.deadline')->requestsFor($order)->first();

        $this->assertNotNull($request->deadline);
        $this->assertTrue($request->deadline->isSameDay($deadline));
        $this->assertSame(2, $request->daysRemaining());
        $this->assertTrue($request->isDueSoon());
        $this->assertFalse($request->isOverdue());
    }

    public function test_the_paths_come_from_the_transition_when_the_host_declares_them(): void
    {
        $deadline = Carbon::now()->addDays(5)->startOfDay();

        [$order] = $this->exchange(answered: false, declareFields: true, deadline: $deadline);

        // No path on the entry: the transition named them.
        $request = $this->entry()->requestsFor($order)->first();

        $this->assertSame('Serve la marca da bollo', $request->note);
        $this->assertNotNull($request->deadline);
        $this->assertTrue($request->deadline->isSameDay($deadline));
    }

    public function test_a_passed_term_reads_as_overdue(): void
    {
        [$order] = $this->exchange(answered: false, deadline: Carbon::now()->subDay()->startOfDay());

        $request = $this->entry()->deadlineField('meta.deadline')->requestsFor($order)->first();

        $this->assertTrue($request->isOverdue());
        $this->assertLessThan(0, $request->daysRemaining());
    }

    public function test_the_limit_keeps_the_newest_exchanges(): void
    {
        [$order, , $requestTransition] = $this->exchange(answered: false);

        $this->logTransition(
            $order,
            $requestTransition,
            'under_review',
            'integration_requested',
            'Mario Rossi',
            '2026-03-12 09:00:00',
        );

        $this->assertCount(2, $this->entry()->requestsFor($order));

        $limited = $this->entry()->limit(1)->requestsFor($order);

        $this->assertCount(1, $limited);
        $this->assertSame('2026-03-12 09:00:00', $limited->first()->requestedAt?->toDateTimeString());
    }

    public function test_a_transition_that_leaves_a_message_is_read_as_a_message(): void
    {
        [$order] = $this->rejection();

        $requests = $this->entry()->requestsFor($order);

        $this->assertCount(1, $requests);

        $message = $requests->first();

        $this->assertTrue($message->isMessage());
        // A message waits for nothing: it is not an open request.
        $this->assertFalse($message->isOpen());
        $this->assertSame('reject', $message->transitionName);
        $this->assertSame('Non ammissibile', $message->note);
        $this->assertSame('rejected', $message->toState);
        $this->assertSame('rejected', $message->toStateLabel);
        // The message wears the colour of the state it moved to.
        $this->assertSame('danger', $message->color);
    }

    /**
     * A decision that left a message: the transition rejects and writes the reason, and the
     * state wears the colour of the outcome.
     *
     * @return array{0: Order}
     */
    private function rejection(): array
    {
        $workflow = $this->createTestWorkflow();

        $underReview = $this->createWorkflowState($workflow, ['name' => 'under_review', 'sort_order' => 0]);
        $rejected = $this->createWorkflowState($workflow, [
            'name' => 'rejected',
            'label' => 'Rejected',
            'color' => 'danger',
            'is_final' => true,
            'sort_order' => 1,
        ]);

        $reject = $this->createWorkflowTransition($workflow, $underReview, $rejected, [
            'name' => 'reject',
            'label' => 'Reject',
            'metadata' => ['leaves_message' => true, 'note_field' => 'rejection_reason'],
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ORE-200',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'state' => 'rejected',
            'form_data' => ['rejection_reason' => 'Non ammissibile'],
        ]);

        $this->logTransition($order, $reject, 'under_review', 'rejected', 'Mario Rossi', '2026-03-08 09:00:00');

        return [$order];
    }

    /**
     * The exchange of the call: a request that the office makes and, when asked, the answer it
     * receives.
     *
     * @return array{0: Order, 1: WorkflowStateTransition, 2: WorkflowTransition}
     */
    private function exchange(
        bool $answered,
        bool $markWithDsl = true,
        ?Carbon $deadline = null,
        bool $declareFields = false,
    ): array {
        $workflow = $this->createTestWorkflow();

        $underReview = $this->createWorkflowState($workflow, ['name' => 'under_review', 'label' => 'Under review', 'sort_order' => 0]);
        $requested = $this->createWorkflowState($workflow, ['name' => 'integration_requested', 'label' => 'Integration requested', 'sort_order' => 1]);

        $requestMetadata = $markWithDsl ? ['opens_request' => true] : [];

        if ($declareFields) {
            $requestMetadata['note_field'] = 'review.notes';
            $requestMetadata['deadline_field'] = 'meta.deadline';
        }

        $request = $this->createWorkflowTransition($workflow, $underReview, $requested, [
            'name' => 'request_integration',
            'label' => 'Request an integration',
            'metadata' => $requestMetadata !== [] ? $requestMetadata : null,
        ]);

        $answer = $this->createWorkflowTransition($workflow, $requested, $underReview, [
            'name' => 'resubmit',
            'label' => 'Send the integration',
            'metadata' => $markWithDsl ? ['answers_request' => true] : null,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-ORE-100',
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
            'state' => 'integration_requested',
            'form_data' => [
                'review' => ['notes' => 'Serve la marca da bollo'],
                'meta' => ['deadline' => $deadline?->toDateTimeString()],
            ],
        ]);

        $open = $this->logTransition($order, $request, 'under_review', 'integration_requested', 'Mario Rossi', '2026-03-08 09:00:00');

        if ($answered) {
            $this->logTransition($order, $answer, 'integration_requested', 'under_review', 'Luisa Bianchi', '2026-03-10 09:00:00');
        }

        return [$order, $open, $request];
    }

    private function logTransition(
        Order $order,
        WorkflowTransition $transition,
        string $from,
        string $to,
        ?string $userName = null,
        ?string $at = null,
    ): WorkflowStateTransition {
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
            'user_name' => $userName,
            'is_visible' => true,
            'created_at' => $at !== null ? Carbon::parse($at) : Carbon::now(),
        ]);
        $row->save();

        return $row;
    }

    private function entry(): OpenRequestsEntry
    {
        return OpenRequestsEntry::make('open-requests');
    }
}
