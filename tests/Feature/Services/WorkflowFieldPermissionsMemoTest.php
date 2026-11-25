<?php

namespace RoBYCoNTe\FilamentFlow\Tests\Feature\Services;

use Illuminate\Support\Facades\DB;
use RoBYCoNTe\FilamentFlow\Models\Workflow;
use RoBYCoNTe\FilamentFlow\Models\WorkflowState;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateField;
use RoBYCoNTe\FilamentFlow\Models\WorkflowStateFieldRole;
use RoBYCoNTe\FilamentFlow\Services\WorkflowFieldPermissionsService;
use RoBYCoNTe\FilamentFlow\Support\Memo;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\Order;
use RoBYCoNTe\FilamentFlow\Tests\Fixtures\Models\User;
use RoBYCoNTe\FilamentFlow\Tests\TestCase;

/**
 * A form asks the permission of every field it draws, and every question begins from the same
 * state and the same roles. Reading a form must not cost a query a field: the state, the roles of
 * the person on the record and the scope of the requests are worked out once for the life of the
 * service, and a service asked for again sees what changed.
 */
class WorkflowFieldPermissionsMemoTest extends TestCase
{
    private Workflow $workflow;

    private WorkflowState $review;

    private User $editor;

    private User $admin;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filament-flow.state_access.enabled', true);
        config()->set('filament-flow.state_access.owner_field', 'user_id');

        $this->workflow = $this->createTestWorkflow();
        $this->review = $this->createWorkflowState($this->workflow, ['name' => 'review', 'label' => 'Review', 'sort_order' => 1]);
        $this->editor = $this->createTestUser(['email' => 'editor@test.com', 'role' => 'editor']);
        $this->admin = $this->createTestUser(['email' => 'admin@test.com', 'role' => 'admin']);

        $field = WorkflowStateField::create([
            'state_id' => $this->review->id,
            'field_name' => 'review_notes',
            'visibility' => 'hidden',
            'mutability' => 'editable',
            'is_required' => false,
        ]);

        WorkflowStateFieldRole::create([
            'state_field_id' => $field->id,
            'role_name' => '@assigned',
            'visibility' => 'visible',
            'mutability' => null,
            'is_required' => null,
        ]);

        $this->order = Order::create([
            'order_number' => 'ORD-MEMO-1',
            'customer_name' => 'Test Customer',
            'total_amount' => 100,
            'state' => 'review',
            'user_id' => $this->editor->id,
        ]);
    }

    private function queriesTouching(string $fragment, callable $work): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $work();
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        return count(array_filter($log, static fn (array $q): bool => str_contains($q['query'], $fragment)));
    }

    public function test_the_state_is_read_once_however_many_fields_are_asked(): void
    {
        $service = new WorkflowFieldPermissionsService;
        $service->permissionFor($this->order, 'review_notes', $this->admin);

        $reads = $this->queriesTouching('workflow_states', function () use ($service): void {
            foreach (range(1, 40) as $i) {
                $service->permissionFor($this->order, "field_{$i}", $this->admin);
            }
        });

        $this->assertSame(0, $reads, 'The state of the record is read again for every field.');
    }

    public function test_the_assignment_of_the_person_is_asked_once_however_many_fields_are_asked(): void
    {
        $this->order->assignTo($this->admin, 'primary', $this->editor);
        $service = new WorkflowFieldPermissionsService;
        $service->permissionFor($this->order, 'review_notes', $this->admin);

        $reads = $this->queriesTouching('workflow_assignments', function () use ($service): void {
            foreach (range(1, 40) as $i) {
                $service->permissionFor($this->order, "field_{$i}", $this->admin);
            }
        });

        $this->assertSame(0, $reads, 'The assignments are asked again for every field.');
    }

    public function test_the_answers_are_the_same_as_without_the_memory(): void
    {
        $this->order->assignTo($this->admin, 'primary', $this->editor);

        $shared = new WorkflowFieldPermissionsService;
        $first = $shared->permissionFor($this->order, 'review_notes', $this->admin);
        $again = $shared->permissionFor($this->order, 'review_notes', $this->admin);
        $fresh = (new WorkflowFieldPermissionsService)->permissionFor($this->order, 'review_notes', $this->admin);

        $this->assertSame($fresh, $first);
        $this->assertSame($fresh, $again);
        $this->assertTrue($first['visible'], 'The assigned person sees the field.');
    }

    public function test_each_person_has_their_own_answer_in_the_same_service(): void
    {
        $this->order->assignTo($this->admin, 'primary', $this->editor);
        $service = new WorkflowFieldPermissionsService;

        $this->assertTrue($service->permissionFor($this->order, 'review_notes', $this->admin)['visible']);
        $this->assertFalse($service->permissionFor($this->order, 'review_notes', $this->editor)['visible']);
        $this->assertTrue($service->permissionFor($this->order, 'review_notes', $this->admin)['visible']);
    }

    public function test_a_service_asked_for_again_sees_an_assignment_made_in_between(): void
    {
        $before = new WorkflowFieldPermissionsService;
        $this->assertFalse($before->permissionFor($this->order, 'review_notes', $this->admin)['visible']);

        $this->order->assignTo($this->admin, 'primary', $this->editor);

        $this->assertTrue((new WorkflowFieldPermissionsService)->permissionFor($this->order, 'review_notes', $this->admin)['visible']);
    }

    public function test_a_state_that_changes_is_read_for_its_new_value(): void
    {
        $service = new WorkflowFieldPermissionsService;
        $service->permissionFor($this->order, 'review_notes', $this->admin);

        $this->order->forceFill(['state' => 'draft'])->save();

        $this->assertNull($service->permissionFor($this->order, 'review_notes', $this->admin), 'The draft has no rule for the field.');
    }

    public function test_a_rule_changed_while_the_request_goes_on_is_seen_by_the_same_person(): void
    {
        // The cache that outlives the request has its own invalidation: what is under test here is
        // the memory of the request, so the cache is out of the way.
        config(['filament-flow.cache.enabled' => false]);

        $service = new WorkflowFieldPermissionsService;
        $this->order->assignTo($this->admin, 'primary', $this->editor);

        $before = $service->permissionFor($this->order, 'review_notes', $this->admin);
        $this->assertFalse($before['readonly']);

        WorkflowStateField::query()->where('field_name', 'review_notes')->firstOrFail()
            ->update(['mutability' => 'readonly']);

        $after = $service->permissionFor($this->order, 'review_notes', $this->admin);

        $this->assertTrue($after['readonly'], 'The rule changed through Eloquent: the same person sees it at the next question.');
    }

    public function test_a_state_that_is_renamed_while_the_request_goes_on_is_seen(): void
    {
        $service = new WorkflowFieldPermissionsService;
        $service->permissionFor($this->order, 'review_notes', $this->admin);

        $this->review->update(['name' => 'under_review']);
        $this->order->forceFill(['state' => 'under_review'])->save();

        $this->assertNotNull($service->permissionFor($this->order, 'review_notes', $this->admin), 'The state kept its rules under its new name.');
    }

    public function test_the_questions_are_answered_from_the_memo(): void
    {
        $memo = app(Memo::class);
        $memo->flush();
        $service = new WorkflowFieldPermissionsService;

        foreach (range(1, 20) as $i) {
            $service->permissionFor($this->order, "field_{$i}", $this->admin);
        }

        $this->assertGreaterThan(30, $memo->stats()['hits'], 'The questions every field asks are answered from memory.');
    }
}
