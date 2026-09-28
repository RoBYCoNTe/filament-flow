<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Infolists;

use Illuminate\Database\Eloquent\Model;
use RoBYCoNTe\FilamentFlow\Infolists\Components\StateBadge;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * The badge of a state: its name, its colour, the mark it wears and — when the call says so —
 * what the state means and how long the record has stood there.
 */
class StateBadgeTest extends TestCase
{
    public function test_the_badge_carries_a_translated_label(): void
    {
        $this->assertSame(
            __('filament-flow::messages.state_badge_label'),
            StateBadge::make()->getLabel(),
        );
    }

    public function test_a_state_wears_the_mark_of_its_kind(): void
    {
        $workflow = $this->createTestWorkflow();

        $this->createWorkflowState($workflow, [
            'name' => 'draft',
            'label' => 'Draft',
            'color' => 'gray',
            'is_initial' => true,
            'sort_order' => 0,
        ]);

        $this->createWorkflowState($workflow, [
            'name' => 'under_review',
            'label' => 'Under review',
            'color' => 'warning',
            'sort_order' => 1,
        ]);

        $this->createWorkflowState($workflow, [
            'name' => 'approved',
            'label' => 'Ammessa',
            'color' => 'success',
            'is_final' => true,
            'sort_order' => 2,
        ]);

        $this->createWorkflowState($workflow, [
            'name' => 'rejected',
            'label' => 'Rigettata',
            'color' => 'danger',
            'is_final' => true,
            'sort_order' => 3,
        ]);

        $this->createWorkflowState($workflow, [
            'name' => 'registered',
            'label' => 'Protocollata',
            'color' => 'info',
            'icon' => 'heroicon-m-inbox-arrow-down',
            'sort_order' => 4,
        ]);

        // The beginning, and a state the call gave an icon of its own.
        $draft = $this->badgeFor('draft');
        $this->assertSame('heroicon-m-play-circle', $draft->getStateMarkerIcon());
        $this->assertFalse($draft->showsStateDot());

        $registered = $this->badgeFor('registered');
        $this->assertSame('heroicon-m-inbox-arrow-down', $registered->getStateMarkerIcon());

        // The end: a refusal and an approval do not read the same.
        $approved = $this->badgeFor('approved');
        $this->assertTrue($approved->getStateIsFinal());
        $this->assertSame('heroicon-m-check-badge', $approved->getStateMarkerIcon());

        $rejected = $this->badgeFor('rejected');
        $this->assertTrue($rejected->getStateIsFinal());
        $this->assertSame('heroicon-m-x-circle', $rejected->getStateMarkerIcon());

        // Nothing to say about a state in the middle: a dot, and the colour of the state.
        $review = $this->badgeFor('under_review');
        $this->assertNull($review->getStateMarkerIcon());
        $this->assertTrue($review->showsStateDot());
        $this->assertSame('warning', $review->getStateColor());
        $this->assertFalse($review->getStateIsFinal());
    }

    public function test_the_description_of_the_state_is_read_only_when_the_host_asks_for_it(): void
    {
        $workflow = $this->createTestWorkflow();

        $this->createWorkflowState($workflow, [
            'name' => 'under_review',
            'label' => 'Under review',
            'description' => 'The application is with the office, waiting for a decision.',
            'sort_order' => 0,
        ]);

        $order = $this->createOrder(['state' => 'under_review']);

        $this->assertNull(StateBadge::make()->model($order)->getStateDescription());
        $this->assertSame(
            'The application is with the office, waiting for a decision.',
            StateBadge::make()->description()->model($order)->getStateDescription(),
        );
    }

    public function test_the_host_can_add_a_line_of_its_own(): void
    {
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, ['name' => 'draft', 'label' => 'Draft', 'sort_order' => 0]);

        $order = $this->createOrder(['state' => 'draft']);

        $badge = StateBadge::make()
            ->model($order)
            ->extra(fn (Model $record): string => 'In this state since '.$record->created_at->format('d/m/Y'));

        $this->assertStringStartsWith('In this state since', (string) $badge->getExtraLine());

        // A host with nothing to say says nothing, rather than an empty line.
        $quiet = StateBadge::make()->model($order)->extra(fn (): ?string => null);
        $this->assertNull($quiet->getExtraLine());

        $this->assertNull(StateBadge::make()->model($order)->getExtraLine());
    }

    public function test_a_state_the_workflow_does_not_declare_still_reads(): void
    {
        $workflow = $this->createTestWorkflow();
        $this->createWorkflowState($workflow, ['name' => 'draft', 'label' => 'Draft', 'sort_order' => 0]);

        $order = $this->createOrder(['state' => 'nowhere']);

        $badge = StateBadge::make()->model($order);

        $this->assertSame('nowhere', $badge->getStateLabel());
        $this->assertNull($badge->getStateMarkerIcon());
        $this->assertFalse($badge->getStateIsFinal());
    }

    private function badgeFor(string $state): StateBadge
    {
        return StateBadge::make()->model($this->createOrder(['state' => $state]));
    }

    /** @param array<string,mixed> $data */
    private function createOrder(array $data = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-BADGE-'.uniqid(),
            'customer_name' => 'Test Customer',
            'total_amount' => 100.00,
        ], $data));
    }
}
