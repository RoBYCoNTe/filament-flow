<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Infolists;

use Illuminate\Support\Facades\Lang;
use RoBYCoNTe\FilamentFlow\Contracts\HasFieldPresentation;
use RoBYCoNTe\FilamentFlow\Infolists\Components\TransitionTimeline;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateTransition;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionMetadata;
use RoBYCoNTe\FilamentFlow\Models\WorkflowTransitionSnapshot;
use RoBYCoNTe\FilamentFlow\Presentation\FieldPresentation;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * Tests for TransitionTimeline component and transition history.
 *
 * Since the Filament Infolists package may not be installed,
 * we test the underlying data model and query logic.
 */
class TransitionTimelineTest extends TestCase
{
    public function test_transition_timeline_source_file_exists(): void
    {
        $this->assertFileExists(
            __DIR__.'/../../../src/Infolists/Components/TransitionTimeline.php'
        );
    }

    public function test_no_transitions_for_new_record(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-TL-001',
            'customer_name' => 'Timeline Customer',
            'total_amount' => 50.00,
            'state' => 'pending',
        ]);

        $count = WorkflowStateTransition::where('transitionable_type', Order::class)
            ->where('transitionable_id', $order->id)
            ->count();

        $this->assertEquals(0, $count);
    }

    public function test_transition_logged_after_state_change(): void
    {
        $workflow = $this->createTestWorkflow();

        $pending = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'label' => 'Pending',
            'is_initial' => true,
            'sort_order' => 0,
        ]);

        $processing = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'label' => 'Processing',
            'sort_order' => 1,
        ]);

        $transition = $this->createWorkflowTransition($workflow, $pending, $processing, [
            'name' => 'start_processing',
            'label' => 'Start Processing',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-TL-002',
            'customer_name' => 'Timeline Customer',
            'total_amount' => 75.00,
            'state' => 'pending',
        ]);

        WorkflowStateTransition::create([
            'transitionable_type' => Order::class,
            'transitionable_id' => $order->id,
            'workflow_id' => $workflow->id,
            'transition_id' => $transition->id,
            'from_state' => 'pending',
            'to_state' => 'processing',
            'from_state_label' => 'Pending',
            'to_state_label' => 'Processing',
            'is_visible' => true,
            'created_at' => now(),
        ]);

        $count = WorkflowStateTransition::where('transitionable_type', Order::class)
            ->where('transitionable_id', $order->id)
            ->count();

        $this->assertEquals(1, $count);

        $lastTransition = $this->getLastTransition($order);
        $this->assertNotNull($lastTransition);
        $this->assertEquals('pending', $lastTransition->from_state);
        $this->assertEquals('processing', $lastTransition->to_state);
    }

    public function test_transitions_ordered_by_date_desc(): void
    {
        $workflow = $this->createTestWorkflow();

        $pending = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'label' => 'Pending',
            'is_initial' => true,
            'sort_order' => 0,
        ]);

        $processing = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'label' => 'Processing',
            'sort_order' => 1,
        ]);

        $shipped = $this->createWorkflowState($workflow, [
            'name' => 'shipped',
            'label' => 'Shipped',
            'sort_order' => 2,
        ]);

        $t1 = $this->createWorkflowTransition($workflow, $pending, $processing);
        $t2 = $this->createWorkflowTransition($workflow, $processing, $shipped);

        $order = Order::create([
            'order_number' => 'ORD-TL-003',
            'customer_name' => 'Timeline Customer',
            'total_amount' => 75.00,
            'state' => 'shipped',
        ]);

        WorkflowStateTransition::create([
            'transitionable_type' => Order::class,
            'transitionable_id' => $order->id,
            'workflow_id' => $workflow->id,
            'transition_id' => $t1->id,
            'from_state' => 'pending',
            'to_state' => 'processing',
            'from_state_label' => 'Pending',
            'to_state_label' => 'Processing',
            'is_visible' => true,
            'created_at' => now()->subMinutes(10),
        ]);

        WorkflowStateTransition::create([
            'transitionable_type' => Order::class,
            'transitionable_id' => $order->id,
            'workflow_id' => $workflow->id,
            'transition_id' => $t2->id,
            'from_state' => 'processing',
            'to_state' => 'shipped',
            'from_state_label' => 'Processing',
            'to_state_label' => 'Shipped',
            'is_visible' => true,
            'created_at' => now(),
        ]);

        $transitions = WorkflowStateTransition::where('transitionable_type', Order::class)
            ->where('transitionable_id', $order->id)
            ->orderByDesc('created_at')
            ->get();

        $this->assertCount(2, $transitions);
        $this->assertEquals('shipped', $transitions[0]->to_state);
        $this->assertEquals('processing', $transitions[1]->to_state);
    }

    public function test_total_transition_count(): void
    {
        $workflow = $this->createTestWorkflow();

        $pending = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'label' => 'Pending',
            'is_initial' => true,
            'sort_order' => 0,
        ]);

        $processing = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'label' => 'Processing',
            'sort_order' => 1,
        ]);

        $transition = $this->createWorkflowTransition($workflow, $pending, $processing);

        $order = Order::create([
            'order_number' => 'ORD-TL-004',
            'customer_name' => 'Test',
            'total_amount' => 50.00,
            'state' => 'processing',
        ]);

        for ($i = 0; $i < 3; $i++) {
            WorkflowStateTransition::create([
                'transitionable_type' => Order::class,
                'transitionable_id' => $order->id,
                'workflow_id' => $workflow->id,
                'transition_id' => $transition->id,
                'from_state' => 'pending',
                'to_state' => 'processing',
                'from_state_label' => 'Pending',
                'to_state_label' => 'Processing',
                'is_visible' => true,
                'created_at' => now()->subMinutes($i),
            ]);
        }

        $count = WorkflowStateTransition::where('transitionable_type', Order::class)
            ->where('transitionable_id', $order->id)
            ->visible()
            ->count();

        $this->assertEquals(3, $count);
    }

    public function test_visible_scope_filters_hidden_transitions(): void
    {
        $workflow = $this->createTestWorkflow();

        $pending = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'label' => 'Pending',
            'is_initial' => true,
            'sort_order' => 0,
        ]);

        $processing = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'label' => 'Processing',
            'sort_order' => 1,
        ]);

        $transition = $this->createWorkflowTransition($workflow, $pending, $processing);

        $order = Order::create([
            'order_number' => 'ORD-TL-005',
            'customer_name' => 'Test',
            'total_amount' => 50.00,
            'state' => 'processing',
        ]);

        WorkflowStateTransition::create([
            'transitionable_type' => Order::class,
            'transitionable_id' => $order->id,
            'workflow_id' => $workflow->id,
            'transition_id' => $transition->id,
            'from_state' => 'pending',
            'to_state' => 'processing',
            'from_state_label' => 'Pending',
            'to_state_label' => 'Processing',
            'is_visible' => true,
            'created_at' => now(),
        ]);

        WorkflowStateTransition::create([
            'transitionable_type' => Order::class,
            'transitionable_id' => $order->id,
            'workflow_id' => $workflow->id,
            'transition_id' => $transition->id,
            'from_state' => 'processing',
            'to_state' => 'pending',
            'from_state_label' => 'Processing',
            'to_state_label' => 'Pending',
            'is_visible' => false,
            'created_at' => now(),
        ]);

        $visibleCount = WorkflowStateTransition::where('transitionable_type', Order::class)
            ->where('transitionable_id', $order->id)
            ->visible()
            ->count();

        $totalCount = WorkflowStateTransition::where('transitionable_type', Order::class)
            ->where('transitionable_id', $order->id)
            ->count();

        $this->assertEquals(1, $visibleCount);
        $this->assertEquals(2, $totalCount);
    }

    /** @see TransitionTimeline */
    public function test_component_has_a_translated_label_and_sensible_defaults(): void
    {
        $component = TransitionTimeline::make();

        $this->assertSame(__('filament-flow::messages.timeline_label'), $component->getLabel());
        $this->assertSame(10, $component->getLimit());
        $this->assertSame(100, $component->getLoadLimit());
        $this->assertTrue($component->isExpandable());
        $this->assertTrue($component->showsMetadata());
        $this->assertFalse($component->showsIpAddress());
        $this->assertFalse($component->showsSnapshots());
        $this->assertStringContainsString('H:i', $component->getDateTimeFormat());
    }

    public function test_host_can_override_every_option(): void
    {
        $component = TransitionTimeline::make()
            ->limit(3)
            ->loadLimit(50)
            ->expandable(false)
            ->showMetadata(false)
            ->showIpAddress()
            ->showSnapshots()
            ->dateTimeFormat('Y-m-d');

        $this->assertSame(3, $component->getLimit());
        $this->assertSame(50, $component->getLoadLimit());
        $this->assertFalse($component->isExpandable());
        $this->assertFalse($component->showsMetadata());
        $this->assertTrue($component->showsIpAddress());
        $this->assertTrue($component->showsSnapshots());
        $this->assertSame('Y-m-d', $component->getDateTimeFormat());
    }

    public function test_load_limit_never_goes_below_the_visible_limit(): void
    {
        $component = TransitionTimeline::make()->limit(10)->loadLimit(5);

        $this->assertSame(10, $component->getLoadLimit());
    }

    public function test_timeline_eager_loads_the_metadata_only_when_asked(): void
    {
        $workflow = $this->createTestWorkflow();

        $pending = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'label' => 'Pending',
            'is_initial' => true,
            'sort_order' => 0,
        ]);

        $processing = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'label' => 'Processing',
            'sort_order' => 1,
        ]);

        $transition = $this->createWorkflowTransition($workflow, $pending, $processing);

        $order = Order::create([
            'order_number' => 'ORD-TL-100',
            'customer_name' => 'Timeline Customer',
            'total_amount' => 50.00,
            'state' => 'processing',
        ]);

        $history = WorkflowStateTransition::create([
            'transitionable_type' => Order::class,
            'transitionable_id' => $order->id,
            'workflow_id' => $workflow->id,
            'transition_id' => $transition->id,
            'from_state' => 'pending',
            'to_state' => 'processing',
            'from_state_label' => 'Pending',
            'to_state_label' => 'Processing',
            'is_visible' => true,
            'created_at' => now(),
        ]);

        WorkflowTransitionMetadata::create([
            'transition_history_id' => $history->id,
            'form_data' => ['amount' => '2.500'],
        ]);

        $eager = TransitionTimeline::make()->model($order)->getTimeline();

        $this->assertTrue($eager->first()->relationLoaded('metadata'));

        $plain = TransitionTimeline::make()
            ->showMetadata(false)
            ->model($order)
            ->getTimeline();

        $this->assertFalse($plain->first()->relationLoaded('metadata'));
    }

    public function test_snapshots_load_only_when_asked_and_the_diff_keeps_what_moved(): void
    {
        $workflow = $this->createTestWorkflow();

        $pending = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'label' => 'Pending',
            'is_initial' => true,
            'sort_order' => 0,
        ]);

        $processing = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'label' => 'Processing',
            'sort_order' => 1,
        ]);

        $transition = $this->createWorkflowTransition($workflow, $pending, $processing);

        $order = Order::create([
            'order_number' => 'ORD-TL-101',
            'customer_name' => 'Timeline Customer',
            'total_amount' => 75.00,
            'state' => 'processing',
        ]);

        $history = WorkflowStateTransition::create([
            'transitionable_type' => Order::class,
            'transitionable_id' => $order->id,
            'workflow_id' => $workflow->id,
            'transition_id' => $transition->id,
            'from_state' => 'pending',
            'to_state' => 'processing',
            'from_state_label' => 'Pending',
            'to_state_label' => 'Processing',
            'is_visible' => true,
            'created_at' => now(),
        ]);

        WorkflowTransitionSnapshot::create([
            'transition_history_id' => $history->id,
            'snapshot_type' => 'before',
            'record_data' => ['total_amount' => 50, 'state' => 'pending', 'note' => 'same'],
        ]);

        WorkflowTransitionSnapshot::create([
            'transition_history_id' => $history->id,
            'snapshot_type' => 'after',
            'record_data' => ['total_amount' => 75, 'state' => 'processing', 'note' => 'same'],
        ]);

        $component = TransitionTimeline::make()->showSnapshots()->model($order);
        $item = $component->getTimeline()->first();

        $this->assertTrue($item->relationLoaded('snapshotBefore'));
        $this->assertTrue($item->relationLoaded('snapshotAfter'));

        $diff = $component->getSnapshotDiff($item);

        $this->assertSame(['total_amount', 'state'], array_column($diff, 'field'));
        $this->assertSame(50, $diff[0]['before']);
        $this->assertSame(75, $diff[0]['after']);

        $quiet = TransitionTimeline::make()->model($order)->getTimeline()->first();

        $this->assertFalse($quiet->relationLoaded('snapshotBefore'));
        $this->assertSame([], TransitionTimeline::make()->getSnapshotDiff($quiet));
    }

    public function test_field_changes_are_counted_only_when_metadata_is_asked(): void
    {
        $workflow = $this->createTestWorkflow();

        $pending = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'label' => 'Pending',
            'is_initial' => true,
            'sort_order' => 0,
        ]);

        $processing = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'label' => 'Processing',
            'sort_order' => 1,
        ]);

        $transition = $this->createWorkflowTransition($workflow, $pending, $processing);

        $order = Order::create([
            'order_number' => 'ORD-TL-102',
            'customer_name' => 'Timeline Customer',
            'total_amount' => 50.00,
            'state' => 'processing',
        ]);

        $history = WorkflowStateTransition::create([
            'transitionable_type' => Order::class,
            'transitionable_id' => $order->id,
            'workflow_id' => $workflow->id,
            'transition_id' => $transition->id,
            'from_state' => 'pending',
            'to_state' => 'processing',
            'from_state_label' => 'Pending',
            'to_state_label' => 'Processing',
            'is_visible' => true,
            'created_at' => now(),
        ]);

        WorkflowTransitionMetadata::create([
            'transition_history_id' => $history->id,
            'field_changes' => [
                'total_amount' => ['from' => 50, 'to' => 75],
                'note' => ['from' => 'a', 'to' => 'b'],
            ],
        ]);

        $asked = TransitionTimeline::make()->model($order);

        $this->assertSame(2, $asked->countFieldChanges($asked->getTimeline()->first()));

        $ignored = TransitionTimeline::make()->showMetadata(false)->model($order);

        $this->assertNull($ignored->countFieldChanges($ignored->getTimeline()->first()));
    }

    public function test_marker_wears_the_colour_the_workflow_gave_the_state(): void
    {
        $workflow = $this->createTestWorkflow();

        $pending = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'label' => 'Pending',
            'color' => 'gray',
            'is_initial' => true,
            'sort_order' => 0,
        ]);

        $processing = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'label' => 'Processing',
            'color' => 'success',
            'icon' => 'heroicon-m-check-circle',
            'sort_order' => 1,
        ]);

        $transition = $this->createWorkflowTransition($workflow, $pending, $processing);

        $order = Order::create([
            'order_number' => 'ORD-TL-103',
            'customer_name' => 'Timeline Customer',
            'total_amount' => 50.00,
            'state' => 'processing',
        ]);

        $history = WorkflowStateTransition::create([
            'transitionable_type' => Order::class,
            'transitionable_id' => $order->id,
            'workflow_id' => $workflow->id,
            'transition_id' => $transition->id,
            'from_state' => 'pending',
            'to_state' => 'processing',
            'from_state_label' => 'Pending',
            'to_state_label' => 'Processing',
            'is_visible' => true,
            'created_at' => now(),
        ]);

        $component = TransitionTimeline::make()->model($order);
        $marker = $component->getMarkerFor($history);

        $this->assertSame('success', $marker['color']);
        $this->assertSame('heroicon-m-check-circle', $marker['icon']);

        $action = $history->replicate()->fill([
            'from_state' => 'processing',
            'transition_id' => null,
            'created_at' => now(),
        ]);
        $action->save();

        $this->assertSame(['color' => null, 'icon' => null], $component->getMarkerFor($action));
    }

    public function test_access_filter_decides_what_the_timeline_counts_and_shows(): void
    {
        $workflow = $this->createTestWorkflow();

        $pending = $this->createWorkflowState($workflow, [
            'name' => 'pending',
            'label' => 'Pending',
            'is_initial' => true,
            'sort_order' => 0,
        ]);

        $processing = $this->createWorkflowState($workflow, [
            'name' => 'processing',
            'label' => 'Processing',
            'sort_order' => 1,
        ]);

        $transition = $this->createWorkflowTransition($workflow, $pending, $processing);

        $order = Order::create([
            'order_number' => 'ORD-TL-104',
            'customer_name' => 'Timeline Customer',
            'total_amount' => 50.00,
            'state' => 'processing',
        ]);

        foreach ([true, false] as $isVisible) {
            WorkflowStateTransition::create([
                'transitionable_type' => Order::class,
                'transitionable_id' => $order->id,
                'workflow_id' => $workflow->id,
                'transition_id' => $transition->id,
                'from_state' => 'pending',
                'to_state' => 'processing',
                'from_state_label' => 'Pending',
                'to_state_label' => 'Processing',
                'is_visible' => $isVisible,
                'created_at' => now(),
            ]);
        }

        $filtered = TransitionTimeline::make()->model($order);

        $this->assertSame(1, $filtered->getTimeline()->count());
        $this->assertSame(1, $filtered->getTotalCount());

        $open = TransitionTimeline::make()->filterByAccess(false)->model($order);

        $this->assertSame(2, $open->getTimeline()->count());
        $this->assertSame(2, $open->getTotalCount());
    }

    public function test_durations_under_a_minute_are_left_out(): void
    {
        $component = TransitionTimeline::make();

        $this->assertNull($component->formatDuration(null));
        $this->assertNull($component->formatDuration(30));
        $this->assertNotNull($component->formatDuration(3600));
    }

    public function test_every_kind_of_value_draws_as_text(): void
    {
        $component = TransitionTimeline::make();

        $this->assertSame('—', $component->formatValue(null));
        $this->assertSame('true', $component->formatValue(true));
        $this->assertSame('text', $component->formatValue('text'));
        $this->assertSame('{"a":1}', $component->formatValue(['a' => 1]));
        $this->assertSame('—', $component->formatValue(["\xB1"]));
    }

    /**
     * The fields the transition moved read in the words of the host: the label, the shape of
     * the value, the block it belongs to — and what the host keeps out of the history stays
     * out of it.
     */
    public function test_changed_fields_read_through_the_host_presentation(): void
    {
        $record = new class extends Order implements HasFieldPresentation
        {
            public function fieldPresentation(string $path, mixed $value): ?FieldPresentation
            {
                return match ($path) {
                    'total_amount' => FieldPresentation::text(
                        'Amount',
                        $value === null ? null : '€ '.number_format((float) $value, 2, '.', ','),
                        'Amounts',
                    ),
                    'internal' => FieldPresentation::hidden('Internal'),
                    default => null,
                };
            }
        };

        $entry = $this->historyEntry([
            'field_changes' => [
                'total_amount' => ['from' => 50, 'to' => 75],
                'internal' => ['from' => 'a', 'to' => 'b'],
                'order_number' => ['from' => null, 'to' => 'ORD-1'],
            ],
        ]);

        $changed = TransitionTimeline::make()->model($record)->getChangedFields($entry);

        $this->assertSame(1, $changed['hidden']);
        $this->assertCount(2, $changed['fields']);

        $this->assertSame('Amount', $changed['fields'][0]['label']);
        $this->assertSame('Amounts', $changed['fields'][0]['group']);
        $this->assertSame('€ 50.00', $changed['fields'][0]['before']->toInlineString());
        $this->assertSame('€ 75.00', $changed['fields'][0]['after']->toInlineString());

        // The label of a path the custom presenter does not claim comes from HasFieldLabels.
        $this->assertSame('Order number', $changed['fields'][1]['label']);
        $this->assertSame('—', $changed['fields'][1]['before']->toInlineString());
        $this->assertSame('ORD-1', $changed['fields'][1]['after']->toInlineString());

        $this->assertSame(
            ['Amounts' => ['total_amount'], '' => ['order_number']],
            array_map(
                static fn (array $rows): array => array_column($rows, 'path'),
                TransitionTimeline::make()->groupFields($changed['fields']),
            ),
        );
    }

    public function test_the_submitted_data_is_flattened_presented_and_grouped(): void
    {
        $record = new class extends Order implements HasFieldPresentation
        {
            public function fieldPresentation(string $path, mixed $value): ?FieldPresentation
            {
                return match ($path) {
                    'applicant.first_name' => FieldPresentation::text('Name', $value, '1. Applicant'),
                    'documents.report' => FieldPresentation::files(
                        'Report',
                        array_map(
                            static fn (string $name): array => ['name' => $name, 'url' => null],
                            $value ?? [],
                        ),
                        '4. Documents',
                    ),
                    default => null,
                };
            }
        };

        $entry = $this->historyEntry([
            'form_data' => [
                'applicant' => ['first_name' => 'Ada', 'email' => null],
                'note' => 'a note',
                'documents' => ['report' => ['report.pdf']],
                'empty_list' => [],
            ],
        ]);

        // The empty paths are left out, and counted: a form carries dozens of them.
        $submitted = TransitionTimeline::make()->model($record)->getSubmittedFields($entry);

        $this->assertSame(2, $submitted['hidden']);
        $this->assertSame(
            ['applicant.first_name', 'note', 'documents.report'],
            array_column($submitted['fields'], 'path'),
        );

        $this->assertSame(
            ['1. Applicant', '', '4. Documents'],
            array_keys(TransitionTimeline::make()->groupFields($submitted['fields'])),
        );

        $this->assertSame(1, count($submitted['fields'][2]['presentation']->value));
        $this->assertSame('report.pdf', $submitted['fields'][2]['presentation']->value[0]['name']);

        // Asking for them shows the empty paths too.
        $everything = TransitionTimeline::make()
            ->hideEmptyFields(false)
            ->model($record)
            ->getSubmittedFields($entry);

        $this->assertSame(0, $everything['hidden']);
        $this->assertCount(5, $everything['fields']);
    }

    public function test_the_changed_fields_are_preferred_over_the_whole_form(): void
    {
        $entry = $this->historyEntry([
            'form_data' => ['note' => 'the whole form'],
        ]);

        $this->assertSame([], TransitionTimeline::make()->getChangedFields($entry)['fields']);
        $this->assertSame(
            ['note'],
            array_column(TransitionTimeline::make()->getSubmittedFields($entry)['fields'], 'path'),
        );
    }

    /**
     * A path nobody claims is named with the words of the key — and those words are asked of
     * the translations first, because a host that translates its own vocabulary has them
     * written down already.
     */
    public function test_the_label_of_an_unknown_path_is_translated_when_the_words_are_known(): void
    {
        // The vocabulary of the host, as a JSON translation file: the same place the words
        // declared by a scheme are translated from.
        $directory = sys_get_temp_dir().'/filament-flow-lang-'.uniqid();
        mkdir($directory);
        file_put_contents($directory.'/en.json', json_encode([
            'Amount' => 'Requested amount',
            'Vat Number' => 'VAT number',
            'applicant.vat_number' => 'VAT number of the applicant',
        ]));

        Lang::addJsonPath($directory);

        $component = TransitionTimeline::make();

        // The words of the key are translated.
        $this->assertSame('Requested amount', $component->presentationFor('intervention.amount', 5)->label);

        // The path is the most precise key, and wins over the headlined words.
        $this->assertSame(
            'VAT number of the applicant',
            $component->presentationFor('applicant.vat_number', '123')->label,
        );

        // Nothing known: the words of the key stand as they are.
        $this->assertSame('Saved At', $component->presentationFor('meta.saved_at', 'now')->label);

        unlink($directory.'/en.json');
        rmdir($directory);
    }

    public function test_the_paths_the_host_hides_never_reach_the_history(): void
    {
        $entry = $this->historyEntry([
            'form_data' => [
                'meta' => ['saved_at' => 'now'],
                'extra' => ['note' => 'x'],
                'applicant' => ['first_name' => 'Ada'],
            ],
        ]);

        $component = TransitionTimeline::make()->hideFields(['extra', 'meta.saved_at']);
        $submitted = $component->getSubmittedFields($entry);

        // The subtree and the exact path are left out, and counted with the empties.
        $this->assertSame(['applicant.first_name'], array_column($submitted['fields'], 'path'));
        $this->assertSame(2, $submitted['hidden']);

        $this->assertTrue($component->hidesPath('extra.note'));
        $this->assertTrue($component->hidesPath('meta.saved_at'));
        $this->assertFalse($component->hidesPath('meta.submitted_at'));
        $this->assertFalse($component->hidesPath('applicant.first_name'));
        $this->assertSame(['extra', 'meta.saved_at'], $component->hiddenFields());
    }

    public function test_a_hidden_path_takes_its_change_with_it(): void
    {
        $entry = $this->historyEntry([
            'field_changes' => [
                'extra.note' => ['from' => 'a', 'to' => 'b'],
                'applicant.first_name' => ['from' => 'Ada', 'to' => 'Grace'],
            ],
        ]);

        $changed = TransitionTimeline::make()->hideFields(['extra.*'])->getChangedFields($entry);

        $this->assertSame(['applicant.first_name'], array_column($changed['fields'], 'path'));
        $this->assertSame(1, $changed['hidden']);
    }

    public function test_whether_the_transition_was_compared_at_all(): void
    {
        // Compared, and nothing moved: an answer of its own.
        $quiet = $this->historyEntry(['field_changes' => []]);
        $component = TransitionTimeline::make();

        $this->assertTrue($component->wasCompared($quiet));
        $this->assertSame([], $component->getChangedFields($quiet)['fields']);

        // No delta at all: nobody looked — the rows logged before the engine recorded them.
        $silent = $this->historyEntry(['form_data' => ['note' => 'x']]);

        $this->assertFalse($component->wasCompared($silent));

        // With the metadata off, there is nothing to look at in the first place.
        $this->assertFalse(TransitionTimeline::make()->showMetadata(false)->wasCompared($quiet));

        // And a host may keep the values of an uncompared entry out of sight.
        $this->assertTrue($component->showsSubmittedData());
        $this->assertFalse(TransitionTimeline::make()->showSubmittedData(false)->showsSubmittedData());
    }

    public function test_groups_fold_only_when_there_is_something_to_navigate(): void
    {
        $component = TransitionTimeline::make();

        // One block of a few fields: read at a glance, no fold.
        $this->assertFalse($component->foldsGroups([
            ['group' => 'Body and contacts'],
            ['group' => 'Body and contacts'],
        ]));

        // Several blocks: navigated.
        $this->assertTrue($component->foldsGroups([
            ['group' => 'Body and contacts'],
            ['group' => 'Self-declarations'],
        ]));

        // One long block: navigated too.
        $this->assertTrue($component->foldsGroups(
            array_fill(0, 9, ['group' => 'Body and contacts']),
        ));

        // A host that wants everything in sight says so.
        $this->assertFalse(TransitionTimeline::make()->collapseGroups(false)->foldsGroups([
            ['group' => 'Body and contacts'],
            ['group' => 'Self-declarations'],
        ]));

        $this->assertTrue(TransitionTimeline::make()->collapsibleGroups());
        $this->assertFalse(TransitionTimeline::make()->collapseGroups(false)->collapsibleGroups());
    }

    /** @param array<string,mixed> $metadata */
    private function historyEntry(array $metadata = []): WorkflowStateTransition
    {
        $entry = WorkflowStateTransition::create([
            'transitionable_type' => Order::class,
            'transitionable_id' => '1',
            'from_state' => 'pending',
            'to_state' => 'processing',
            'from_state_label' => 'Pending',
            'to_state_label' => 'Processing',
            'is_visible' => true,
            'created_at' => now(),
        ]);

        if ($metadata !== []) {
            WorkflowTransitionMetadata::create(array_merge(
                ['transition_history_id' => $entry->id],
                $metadata,
            ));
        }

        return $entry->refresh();
    }
}
